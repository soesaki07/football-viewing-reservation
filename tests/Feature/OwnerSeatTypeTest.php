<?php

namespace Tests\Feature;

use App\Http\Requests\SeatTypeRequest;
use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\FootballMatch;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use App\Policies\SeatTypePolicy;
use App\Policies\ShopPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class OwnerSeatTypeTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-31 12:00:00');

        $this->owner = User::factory()->shopOwner()->create();
        $this->shop = Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーA', 'status' => 'published']);
    }

    private function addSeatType(Shop $shop, string $name, bool $isActive = true, int $capacity = 10, int $price = 3000): SeatType
    {
        return SeatType::forceCreate([
            'shop_id' => $shop->id,
            'name' => $name,
            'default_capacity' => $capacity,
            'default_price' => $price,
            'is_active' => $isActive,
        ]);
    }

    private function otherShop(): Shop
    {
        return Shop::factory()->create(['user_id' => User::factory()->shopOwner()->create()->id, 'name' => 'バーB', 'status' => 'published']);
    }

    /**
     * 放映と、その座席（座席種別を使った放映の座席）を作る。
     */
    private function addBroadcastSeat(SeatType $seatType, ?FootballMatch $match = null): BroadcastSeatType
    {
        $broadcast = Broadcast::factory()->forMatch($match ?? $this->createFootballMatch())->create([
            'shop_id' => $seatType->shop_id,
            'status' => Broadcast::STATUS_PUBLISHED,
            'reservation_start_at' => now()->subDay(),
            'reservation_end_at' => now()->addDay(),
        ]);

        return BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides  null を指定した項目は送らない
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        $input = array_merge([
            'shop_id' => $this->shop->id,
            'name' => 'ソファ席',
            'description' => 'ゆったり座れます',
            'default_capacity' => '8',
            'default_price' => '3500',
        ], $overrides);

        return array_filter($input, fn ($value) => $value !== null);
    }

    private function store(array $input)
    {
        return $this->actingAs($this->owner)->post(route('owner.seat-types.store'), $input);
    }

    private function update(SeatType $seatType, array $input)
    {
        return $this->actingAs($this->owner)->put(route('owner.seat-types.update', $seatType), $input);
    }

    // ---- 認可 ----

    public function test_guest_is_redirected_to_login(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');

        $this->get(route('owner.seat-types.index'))->assertRedirect(route('login'));
        $this->get(route('owner.seat-types.create'))->assertRedirect(route('login'));
        $this->post(route('owner.seat-types.store'), $this->validInput())->assertRedirect(route('login'));
        $this->get(route('owner.seat-types.edit', $seatType))->assertRedirect(route('login'));
        $this->put(route('owner.seat-types.update', $seatType), $this->validInput())->assertRedirect(route('login'));
        $this->patch(route('owner.seat-types.active', $seatType), ['is_active' => '0'])->assertRedirect(route('login'));
    }

    public function test_user_and_admin_get_403(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $seatType = $this->addSeatType($this->shop, 'カウンター席');

        foreach ([User::factory()->create(), $admin] as $user) {
            $this->actingAs($user)->get(route('owner.seat-types.index'))->assertForbidden();
            $this->actingAs($user)->get(route('owner.seat-types.create'))->assertForbidden();
            $this->actingAs($user)->post(route('owner.seat-types.store'), $this->validInput())->assertForbidden();
            $this->actingAs($user)->get(route('owner.seat-types.edit', $seatType))->assertForbidden();
            $this->actingAs($user)->put(route('owner.seat-types.update', $seatType), $this->validInput())->assertForbidden();
            $this->actingAs($user)->patch(route('owner.seat-types.active', $seatType), ['is_active' => '0'])->assertForbidden();
        }

        $this->assertDatabaseCount('seat_types', 1);
        $this->assertTrue((bool) $seatType->fresh()->is_active);
    }

    public function test_other_owners_seat_type_returns_404_and_is_not_changed(): void
    {
        $other = $this->addSeatType($this->otherShop(), '他店の席');

        $this->actingAs($this->owner)->get(route('owner.seat-types.edit', $other))->assertNotFound();
        $this->update($other, $this->validInput(['name' => '乗っ取り']))->assertNotFound();
        $this->actingAs($this->owner)->patch(route('owner.seat-types.active', $other), ['is_active' => '0'])->assertNotFound();

        $other->refresh();
        $this->assertSame('他店の席', $other->name);
        $this->assertTrue((bool) $other->is_active);
    }

    public function test_seat_type_of_deleted_shop_returns_404(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');
        $this->shop->delete();

        $this->actingAs($this->owner)->get(route('owner.seat-types.edit', $seatType))->assertNotFound();
        $this->update($seatType, $this->validInput())->assertNotFound();
        $this->actingAs($this->owner)->patch(route('owner.seat-types.active', $seatType), ['is_active' => '0'])->assertNotFound();
        $this->assertTrue((bool) $seatType->fresh()->is_active);
    }

    public function test_store_with_other_owners_shop_id_returns_404(): void
    {
        $this->store($this->validInput(['shop_id' => $this->otherShop()->id]))->assertNotFound();

        $this->assertDatabaseCount('seat_types', 0);
    }

    public function test_store_with_deleted_or_missing_shop_id_returns_404(): void
    {
        $deleted = Shop::factory()->create(['user_id' => $this->owner->id, 'status' => 'published']);
        $deleted->delete();

        $this->store($this->validInput(['shop_id' => $deleted->id]))->assertNotFound();
        $this->store($this->validInput(['shop_id' => $deleted->id + 1000]))->assertNotFound();
        $this->store($this->validInput(['shop_id' => null]))->assertNotFound();
        $this->store($this->validInput(['shop_id' => ['x']]))->assertNotFound();

        $this->assertDatabaseCount('seat_types', 0);
    }

    public function test_authorization_runs_before_validation(): void
    {
        $other = $this->addSeatType($this->otherShop(), '他店の席');

        // 入力が不正でも、他店は検証エラーではなく 404（存在を隠す）
        $this->store($this->validInput(['shop_id' => $other->shop_id, 'name' => null]))->assertNotFound();
        $this->update($other, ['name' => ''])->assertNotFound();
    }

    public function test_policies_compare_ids_as_integers(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');
        // DB ドライバによっては user_id が文字列で返る
        $this->shop->user_id = (string) $this->owner->id;

        $this->assertTrue((new ShopPolicy)->manageSeatTypes($this->owner, $this->shop)->allowed());

        $seatType->setRelation('shop', $this->shop);
        $this->assertTrue((new SeatTypePolicy)->update($this->owner, $seatType)->allowed());
    }

    // ---- 店舗の選択 ----

    public function test_index_with_invalid_shop_parameter_returns_404(): void
    {
        $this->actingAs($this->owner)->get(route('owner.seat-types.index', ['shop' => $this->otherShop()->id]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.seat-types.index', ['shop' => 999999]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.seat-types.index', ['shop' => ['a']]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.seat-types.create', ['shop' => $this->otherShop()->id]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.seat-types.create', ['shop' => ['a']]))->assertNotFound();
    }

    public function test_index_defaults_to_smallest_shop_id_and_can_switch(): void
    {
        $second = Shop::factory()->create(['user_id' => $this->owner->id, 'name' => 'バーC', 'status' => 'published']);
        $this->addSeatType($this->shop, 'カウンター席');
        $this->addSeatType($second, 'ボックス席');

        $this->actingAs($this->owner)->get(route('owner.seat-types.index'))
            ->assertOk()
            ->assertViewHas('shop', fn (Shop $shop) => $shop->is($this->shop))
            ->assertSee('カウンター席')
            ->assertDontSee('ボックス席');

        $this->actingAs($this->owner)->get(route('owner.seat-types.index', ['shop' => $second->id]))
            ->assertOk()
            ->assertViewHas('shop', fn (Shop $shop) => $shop->is($second))
            ->assertSee('ボックス席')
            ->assertDontSee('カウンター席');
    }

    public function test_owner_without_shop_sees_empty_message(): void
    {
        $owner = User::factory()->shopOwner()->create();

        $this->actingAs($owner)->get(route('owner.seat-types.index'))->assertOk()->assertSee('店舗が登録されていません。');
        $this->actingAs($owner)->get(route('owner.seat-types.create'))->assertOk()->assertSee('店舗が登録されていません。');
    }

    // ---- 一覧 ----

    public function test_index_orders_active_first_then_id_and_counts_broadcasts_without_n_plus_one(): void
    {
        $inactive = $this->addSeatType($this->shop, '無効な席', false);
        $first = $this->addSeatType($this->shop, 'カウンター席');
        $second = $this->addSeatType($this->shop, 'テーブル席');
        // 他店の座席種別は出ない
        $this->addSeatType($this->otherShop(), '他店の席');
        $this->addBroadcastSeat($second);
        $this->addBroadcastSeat($second);
        $this->addBroadcastSeat($inactive);

        // ログインユーザーの関連の読み込みなど、初回だけのクエリを済ませてから数える
        $this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertOk();
        DB::enableQueryLog();
        $response = $this->actingAs($this->owner)->get(route('owner.seat-types.index'));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()->assertViewHas('seatTypes', function (Collection $seatTypes) use ($first, $second, $inactive) {
            $this->assertSame([$first->id, $second->id, $inactive->id], $seatTypes->pluck('id')->all());
            $this->assertSame([0, 2, 1], $seatTypes->pluck('broadcast_seat_types_count')->all());

            return true;
        });
        $response->assertSeeInOrder(['カウンター席', 'テーブル席', '無効な席'])
            ->assertDontSee('他店の席')
            ->assertSee('有効')
            ->assertSee('無効')
            ->assertSee('3,000円');

        // 座席種別を増やしてもクエリ数が増えない
        $this->addSeatType($this->shop, '追加の席');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertOk();
        $this->assertSame($queryCount, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_index_shows_confirm_for_deactivate_and_activate_button_for_inactive(): void
    {
        $active = $this->addSeatType($this->shop, 'カウンター席');
        $inactive = $this->addSeatType($this->shop, '無効な席', false);

        $html = $this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm="「カウンター席」を無効にしますか？', $html);
        $this->assertStringNotContainsString('data-confirm="「無効な席」', $html);
        $this->assertStringContainsString('action="'.route('owner.seat-types.active', $active).'"', $html);
        $this->assertStringContainsString('action="'.route('owner.seat-types.active', $inactive).'"', $html);
        $this->assertStringContainsString('無効にする', $html);
        $this->assertStringContainsString('有効にする', $html);
    }

    // ---- 登録 ----

    public function test_create_page_shows_form_with_shop_id(): void
    {
        $this->actingAs($this->owner)->get(route('owner.seat-types.create', ['shop' => $this->shop->id]))
            ->assertOk()
            ->assertSee('name="shop_id" value="'.$this->shop->id.'"', false)
            ->assertSee('登録済みの放映の席数・料金は変わりません');
    }

    public function test_owner_can_store_seat_type(): void
    {
        $response = $this->store($this->validInput());

        $response->assertRedirect(route('owner.seat-types.index', ['shop' => $this->shop->id]));
        $response->assertSessionHas('success', '座席種別を登録しました。');
        $seatType = SeatType::sole();
        $this->assertSame($this->shop->id, $seatType->shop_id);
        $this->assertSame('ソファ席', $seatType->name);
        $this->assertSame('ゆったり座れます', $seatType->description);
        $this->assertSame(8, $seatType->default_capacity);
        $this->assertSame(3500, $seatType->default_price);
        $this->assertTrue((bool) $seatType->is_active);

        $this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertSee('座席種別を登録しました。');
    }

    public function test_store_ignores_is_active_and_extra_input(): void
    {
        $this->store($this->validInput(['is_active' => '0', 'id' => 99999]))->assertRedirect();

        $seatType = SeatType::sole();
        $this->assertTrue((bool) $seatType->is_active);
        $this->assertNotSame(99999, $seatType->id);
    }

    public function test_store_trims_name_and_allows_empty_description(): void
    {
        $this->store($this->validInput(['name' => '  ソファ席  ', 'description' => '']))->assertRedirect();

        $seatType = SeatType::sole();
        $this->assertSame('ソファ席', $seatType->name);
        $this->assertNull($seatType->description);
    }

    public function test_store_with_description_omitted_is_allowed(): void
    {
        $this->store($this->validInput(['description' => null]))->assertRedirect();

        $this->assertNull(SeatType::sole()->description);
    }

    // ---- 入力チェック ----

    public function test_name_is_required_and_whitespace_only_is_rejected(): void
    {
        $this->store($this->validInput(['name' => null]))->assertSessionHasErrors(['name' => '名前を入力してください。']);
        $this->store($this->validInput(['name' => '   ']))->assertSessionHasErrors(['name' => '名前を入力してください。']);

        $this->assertDatabaseCount('seat_types', 0);
    }

    public function test_name_length_boundary(): void
    {
        $this->store($this->validInput(['name' => str_repeat('あ', SeatTypeRequest::MAX_NAME_LENGTH)]))->assertSessionDoesntHaveErrors();
        $this->assertSame(1, SeatType::count());

        $this->store($this->validInput(['name' => str_repeat('あ', SeatTypeRequest::MAX_NAME_LENGTH + 1)]))
            ->assertSessionHasErrors(['name' => '名前は100文字以内で入力してください。']);
        $this->assertSame(1, SeatType::count());
    }

    public function test_description_length_boundary(): void
    {
        $this->store($this->validInput(['name' => 'A席', 'description' => str_repeat('あ', SeatTypeRequest::MAX_DESCRIPTION_LENGTH)]))->assertSessionDoesntHaveErrors();
        $this->assertSame(1, SeatType::count());

        $this->store($this->validInput(['name' => 'B席', 'description' => str_repeat('あ', SeatTypeRequest::MAX_DESCRIPTION_LENGTH + 1)]))
            ->assertSessionHasErrors(['description' => '説明は500文字以内で入力してください。']);
        $this->assertSame(1, SeatType::count());
    }

    public function test_capacity_boundary(): void
    {
        foreach ([1, 65535] as $capacity) {
            $this->store($this->validInput(['name' => "席{$capacity}", 'default_capacity' => (string) $capacity]))->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(2, SeatType::count());
        $this->assertSame(65535, SeatType::where('name', '席65535')->sole()->default_capacity);

        $this->store($this->validInput(['name' => '席0', 'default_capacity' => '0']))->assertSessionHasErrors(['default_capacity' => '既定の席数は1以上で入力してください。']);
        $this->store($this->validInput(['name' => '席65536', 'default_capacity' => '65536']))->assertSessionHasErrors(['default_capacity' => '既定の席数は65535以下で入力してください。']);
        $this->assertSame(2, SeatType::count());
    }

    public function test_price_boundary(): void
    {
        foreach ([0, 1000000] as $price) {
            $this->store($this->validInput(['name' => "席{$price}", 'default_price' => (string) $price]))->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(2, SeatType::count());
        $this->assertSame(1000000, SeatType::where('name', '席1000000')->sole()->default_price);

        $this->store($this->validInput(['name' => '席m1', 'default_price' => '-1']))->assertSessionHasErrors(['default_price' => '既定の料金は0以上で入力してください。']);
        $this->store($this->validInput(['name' => '席over', 'default_price' => '1000001']))->assertSessionHasErrors(['default_price' => '既定の料金は1,000,000以下で入力してください。']);
        $this->assertSame(2, SeatType::count());
    }

    public function test_required_numbers_and_non_integers_are_rejected(): void
    {
        $this->store($this->validInput(['default_capacity' => null]))->assertSessionHasErrors(['default_capacity' => '既定の席数を入力してください。']);
        $this->store($this->validInput(['default_price' => null]))->assertSessionHasErrors(['default_price' => '既定の料金を入力してください。']);
        $this->store($this->validInput(['default_capacity' => '1.5']))->assertSessionHasErrors(['default_capacity' => '既定の席数は整数で入力してください。']);
        $this->store($this->validInput(['default_price' => 'abc']))->assertSessionHasErrors(['default_price' => '既定の料金は整数で入力してください。']);

        $this->assertDatabaseCount('seat_types', 0);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function malformedInputProvider(): array
    {
        return [
            'name が配列' => ['name', ['a']],
            'description が配列' => ['description', ['a']],
            'default_capacity が配列' => ['default_capacity', ['1']],
            'default_price が配列' => ['default_price', ['1']],
        ];
    }

    #[DataProvider('malformedInputProvider')]
    public function test_malformed_input_returns_validation_error_not_500(string $field, mixed $value): void
    {
        $this->store($this->validInput([$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('seat_types', 0);

        $seatType = $this->addSeatType($this->shop, 'カウンター席');
        $this->update($seatType, $this->validInput([$field => $value]))->assertSessionHasErrors($field);
        $this->assertSame('カウンター席', $seatType->fresh()->name);
    }

    public function test_active_value_malformed_returns_validation_error_not_500(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');

        foreach ([['is_active' => ['0']], ['is_active' => 'abc'], []] as $input) {
            $this->actingAs($this->owner)->patch(route('owner.seat-types.active', $seatType), $input)->assertSessionHasErrors('is_active');
        }
        $this->assertTrue((bool) $seatType->fresh()->is_active);
    }

    // ---- 名前の重複 ----

    public function test_duplicate_name_in_same_shop_is_rejected_including_inactive(): void
    {
        $this->addSeatType($this->shop, 'ソファ席');
        $this->addSeatType($this->shop, '休止中の席', false);

        $this->store($this->validInput(['name' => 'ソファ席']))->assertSessionHasErrors(['name' => 'この名前の座席種別はすでにあります。']);
        $this->store($this->validInput(['name' => '休止中の席']))->assertSessionHasErrors(['name' => 'この名前の座席種別はすでにあります。']);

        $this->assertSame(2, SeatType::count());
    }

    public function test_same_name_in_other_shop_is_allowed(): void
    {
        $this->addSeatType($this->otherShop(), 'ソファ席');

        $this->store($this->validInput(['name' => 'ソファ席']))->assertSessionDoesntHaveErrors();

        $this->assertSame(2, SeatType::where('name', 'ソファ席')->count());
    }

    public function test_update_to_other_seat_types_name_is_rejected_but_own_name_is_allowed(): void
    {
        $sofa = $this->addSeatType($this->shop, 'ソファ席');
        $counter = $this->addSeatType($this->shop, 'カウンター席');

        $this->update($counter, $this->validInput(['name' => 'ソファ席']))
            ->assertSessionHasErrors(['name' => 'この名前の座席種別はすでにあります。']);
        $this->assertSame('カウンター席', $counter->fresh()->name);

        // 自分自身の名前のままで他の項目を変えるのは可
        $this->update($sofa, $this->validInput(['name' => 'ソファ席', 'default_price' => '4000']))->assertSessionDoesntHaveErrors();
        $this->assertSame(4000, $sofa->fresh()->default_price);
    }

    public function test_concurrent_duplicate_on_store_returns_to_form_instead_of_500(): void
    {
        // 検証を通ったあと、保存の直前に同じ名前が登録された状況を作る
        SeatType::creating(function (SeatType $seatType) {
            DB::table('seat_types')->insert([
                'shop_id' => $seatType->shop_id,
                'name' => $seatType->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->store($this->validInput());

        $response->assertRedirect(route('owner.seat-types.create', ['shop' => $this->shop->id]));
        $response->assertSessionHasErrors(['name' => 'この名前の座席種別はすでにあります。']);
        $response->assertSessionHasInput('name', 'ソファ席');
        // 先に入った1件だけが残る
        $this->assertSame(1, SeatType::count());
    }

    public function test_concurrent_duplicate_on_update_returns_to_form_instead_of_500(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');
        SeatType::updating(function (SeatType $updating) {
            DB::table('seat_types')->insert([
                'shop_id' => $updating->shop_id,
                'name' => $updating->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->update($seatType, $this->validInput(['name' => 'ソファ席']));

        $response->assertRedirect(route('owner.seat-types.edit', $seatType));
        $response->assertSessionHasErrors(['name' => 'この名前の座席種別はすでにあります。']);
        $this->assertSame('カウンター席', $seatType->fresh()->name);
    }

    // ---- 編集 ----

    public function test_edit_page_shows_current_values_and_notice(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席', true, 12, 2800);
        $this->addBroadcastSeat($seatType);

        $response = $this->actingAs($this->owner)->get(route('owner.seat-types.edit', $seatType))->assertOk();
        // 入力欄ごとに、名前と値の組で確かめる（他の入力欄の値と重ならないようにする）
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<input[^>]*(?=[^>]*name="name")(?=[^>]*value="カウンター席")[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*(?=[^>]*name="default_capacity")(?=[^>]*value="12")[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*(?=[^>]*name="default_price")(?=[^>]*value="2800")[^>]*>/', $html);
        $response
            ->assertSee('登録済みの放映の席数・料金は変わりません')
            ->assertSee('この座席種別を使っている放映：1件');
    }

    public function test_owner_can_update_seat_type(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');

        $response = $this->update($seatType, $this->validInput(['name' => '新カウンター席', 'description' => null, 'default_capacity' => '6', 'default_price' => '0']));

        $response->assertRedirect(route('owner.seat-types.index', ['shop' => $this->shop->id]));
        $response->assertSessionHas('success', '座席種別を更新しました。');
        $seatType->refresh();
        $this->assertSame('新カウンター席', $seatType->name);
        $this->assertNull($seatType->description);
        $this->assertSame(6, $seatType->default_capacity);
        $this->assertSame(0, $seatType->default_price);
        // 店舗・有効状態は変わらない
        $this->assertSame($this->shop->id, $seatType->shop_id);
        $this->assertTrue((bool) $seatType->is_active);
    }

    public function test_update_cannot_move_seat_type_to_other_shop_or_change_active_state(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');
        $otherShop = $this->otherShop();

        $this->update($seatType, $this->validInput(['shop_id' => $otherShop->id, 'is_active' => '0']))->assertRedirect();

        $seatType->refresh();
        $this->assertSame($this->shop->id, $seatType->shop_id);
        $this->assertTrue((bool) $seatType->is_active);
    }

    public function test_changing_defaults_does_not_change_existing_broadcast_seats(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席', true, 10, 3000);
        $seat = $this->addBroadcastSeat($seatType);
        $seat->update(['capacity' => 7, 'price' => 2500]);

        $this->update($seatType, $this->validInput(['name' => 'カウンター席', 'default_capacity' => '20', 'default_price' => '9000']))->assertRedirect();

        $seat->refresh();
        $this->assertSame(7, $seat->capacity);
        $this->assertSame(2500, $seat->price);
        $this->assertSame(20, $seatType->fresh()->default_capacity);
    }

    // ---- 無効化・有効化 ----

    public function test_owner_can_deactivate_and_reactivate(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席');
        $url = route('owner.seat-types.active', $seatType);

        $response = $this->actingAs($this->owner)->patch($url, ['is_active' => '0']);
        $response->assertRedirect(route('owner.seat-types.index', ['shop' => $this->shop->id]));
        $response->assertSessionHas('success', '座席種別を無効にしました。');
        $this->assertFalse((bool) $seatType->fresh()->is_active);

        // 二重送信しても無効のまま（トグルではない）
        $this->actingAs($this->owner)->patch($url, ['is_active' => '0'])->assertRedirect();
        $this->assertFalse((bool) $seatType->fresh()->is_active);

        $response = $this->actingAs($this->owner)->patch($url, ['is_active' => '1']);
        $response->assertSessionHas('success', '座席種別を有効にしました。');
        $this->assertTrue((bool) $seatType->fresh()->is_active);
    }

    public function test_deactivate_only_changes_is_active(): void
    {
        $seatType = $this->addSeatType($this->shop, 'カウンター席', true, 12, 2800);

        $this->actingAs($this->owner)->patch(route('owner.seat-types.active', $seatType), ['is_active' => '0', 'name' => '改名', 'shop_id' => $this->otherShop()->id]);

        $seatType->refresh();
        $this->assertSame('カウンター席', $seatType->name);
        $this->assertSame($this->shop->id, $seatType->shop_id);
        $this->assertSame(12, $seatType->default_capacity);
    }

    // ---- 放映・予約との整合 ----

    public function test_registered_seat_type_can_be_used_in_broadcast_registration(): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        $this->store($this->validInput(['name' => 'ソファ席', 'default_capacity' => '8', 'default_price' => '3500']))->assertRedirect();
        $seatType = SeatType::sole();

        // 登録画面に既定値つきで出る
        $html = $this->actingAs($this->owner)->get(route('owner.broadcasts.create', ['shop' => $this->shop->id, 'competition' => $match->competition_id, 'match' => $match->id]))
            ->assertOk()
            ->assertSee('ソファ席')
            ->getContent();
        $id = $seatType->id;
        $this->assertMatchesRegularExpression('/<input[^>]*(?=[^>]*name="seats\['.$id.'\]\[capacity\]")(?=[^>]*value="8")[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*(?=[^>]*name="seats\['.$id.'\]\[price\]")(?=[^>]*value="3500")[^>]*>/', $html);

        // 放映の登録で選べる
        $this->actingAs($this->owner)->post(route('owner.broadcasts.store'), [
            'shop_id' => $this->shop->id,
            'football_match_id' => $match->id,
            'reservation_start_at' => '2026-10-31T21:00',
            'reservation_end_at' => '2026-11-05T21:00',
            'status' => Broadcast::STATUS_PUBLISHED,
            'seats' => [$seatType->id => ['selected' => '1', 'capacity' => '8', 'price' => '3500', 'max_people_per_reservation' => '4']],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame($seatType->id, BroadcastSeatType::sole()->seat_type_id);
    }

    public function test_broadcast_create_without_seat_types_links_to_seat_type_create(): void
    {
        $this->actingAs($this->owner)->get(route('owner.broadcasts.create', ['shop' => $this->shop->id]))
            ->assertOk()
            ->assertSee('href="'.route('owner.seat-types.create', ['shop' => $this->shop->id]).'"', false);
    }

    public function test_deactivated_seat_type_disappears_from_broadcast_registration_and_reservation(): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        $active = $this->addSeatType($this->shop, 'カウンター席');
        $target = $this->addSeatType($this->shop, 'ソファ席');
        $seat = $this->addBroadcastSeat($target, $match);
        $user = User::factory()->create();
        $reservation = Reservation::factory()->create([
            'broadcast_seat_type_id' => $seat->id,
            'user_id' => $user->id,
            'number_of_people' => 2,
            'unit_price' => $seat->price,
            'total_price' => $seat->price * 2,
        ]);
        $newMatch = $this->createFootballMatch(['kickoff_at' => '2026-11-06 12:00:00']);
        // 同じ試合にすでに予約があるユーザーは二重予約防止で弾かれるので、別のユーザーで確かめる
        $other = User::factory()->create();

        // 無効にする前は、予約の入力に出る
        $this->actingAs($other)->get(route('reservations.create', $seat->broadcast_id))
            ->assertOk()
            ->assertSee('name="broadcast_seat_type_id" value="'.$seat->id.'"', false);

        $this->actingAs($this->owner)->patch(route('owner.seat-types.active', $target), ['is_active' => '0'])->assertRedirect();

        // 放映の登録の選択肢に出ない（有効な座席種別は出る）
        $this->actingAs($this->owner)->get(route('owner.broadcasts.create', ['shop' => $this->shop->id, 'competition' => $newMatch->competition_id, 'match' => $newMatch->id]))
            ->assertOk()
            ->assertSee('name="seats['.$active->id.'][selected]"', false)
            ->assertDontSee('name="seats['.$target->id.'][selected]"', false);

        // 予約の入力に出ない
        $this->actingAs($other)->get(route('reservations.create', $seat->broadcast_id))
            ->assertOk()
            ->assertDontSee('name="broadcast_seat_type_id" value="'.$seat->id.'"', false);

        // 新しい予約はできない
        $this->actingAs($other)->post(route('reservations.confirm', $seat->broadcast_id), [
            'broadcast_seat_type_id' => $seat->id,
            'number_of_people' => 1,
        ])->assertSessionHasErrors('broadcast_seat_type_id');
        $this->assertSame(1, Reservation::count());

        // 既存の予約は残る
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);

        // 有効に戻すと、また予約の入力に出る
        $this->actingAs($this->owner)->patch(route('owner.seat-types.active', $target), ['is_active' => '1'])->assertRedirect();
        $this->actingAs($other)->get(route('reservations.create', $seat->broadcast_id))
            ->assertSee('name="broadcast_seat_type_id" value="'.$seat->id.'"', false);
    }

    public function test_seat_type_deactivated_after_confirm_screen_cannot_be_reserved(): void
    {
        $match = $this->createFootballMatch(['kickoff_at' => '2026-11-05 12:00:00']);
        $seatType = $this->addSeatType($this->shop, 'ソファ席');
        $seat = $this->addBroadcastSeat($seatType, $match);
        $user = User::factory()->create();

        // 確認画面までは通る
        $this->actingAs($user)->post(route('reservations.confirm', $seat->broadcast_id), [
            'broadcast_seat_type_id' => $seat->id,
            'number_of_people' => 1,
        ])->assertOk();

        // 確認画面のあとに、座席種別が無効になった
        $this->actingAs($this->owner)->patch(route('owner.seat-types.active', $seatType), ['is_active' => '0'])->assertRedirect();

        // 確定を送ってもエラーになり、予約は増えない
        $this->actingAs($user)->post(route('reservations.store', $seat->broadcast_id))
            ->assertRedirect(route('reservations.create', $seat->broadcast_id))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_shop_id_is_not_mass_assignable(): void
    {
        $other = $this->otherShop();

        $seatType = new SeatType;
        $seatType->fill(['shop_id' => $other->id, 'name' => '席']);

        $this->assertNull($seatType->shop_id);
        $this->assertSame('席', $seatType->name);
    }

    public function test_header_has_seat_management_link_for_owner_only(): void
    {
        $this->actingAs($this->owner)->get(route('owner.seat-types.index'))
            ->assertSee('href="'.route('owner.seat-types.index').'"', false);

        $this->actingAs(User::factory()->create())->get(route('matches.index'))
            ->assertDontSee('href="'.route('owner.seat-types.index').'"', false);
    }
}
