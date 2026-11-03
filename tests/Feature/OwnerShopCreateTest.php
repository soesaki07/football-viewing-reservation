<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnerShopCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->shopOwner()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => '新しい店名',
            'postal_code' => '1500001',
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'address_line' => '神宮前1-1-1',
            'phone_number' => '03-9999-8888',
            'description' => '新しい説明',
            'opening_time' => '18:00',
            'closing_time' => '02:00',
            'website_url' => 'https://example.com/bar',
        ], $overrides);
    }

    private function store(array $input)
    {
        return $this->actingAs($this->owner)->post(route('owner.shops.store'), $input);
    }

    // ---- 認可 ----

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('owner.shops.create'))->assertRedirect(route('login'));
        $this->post(route('owner.shops.store'), $this->validInput())->assertRedirect(route('login'));

        $this->assertDatabaseCount('shops', 0);
    }

    public function test_user_and_admin_get_403_even_with_invalid_input(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        foreach ([User::factory()->create(), $admin] as $user) {
            $this->actingAs($user)->get(route('owner.shops.create'))->assertForbidden();
            $this->actingAs($user)->post(route('owner.shops.store'), $this->validInput())->assertForbidden();
            // 認可は入力の検証より先（不正な入力でも 403）
            $this->actingAs($user)->post(route('owner.shops.store'), ['name' => ''])->assertForbidden();
        }

        $this->assertDatabaseCount('shops', 0);
    }

    public function test_policy_allows_only_shop_owner_to_create(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        $this->assertTrue(Gate::forUser($this->owner)->allows('create', Shop::class));
        $this->assertTrue(Gate::forUser(User::factory()->create())->denies('create', Shop::class));
        $this->assertTrue(Gate::forUser($admin)->denies('create', Shop::class));
    }

    // ---- 正常系 ----

    public function test_create_form_is_shown_without_status_field(): void
    {
        $this->actingAs($this->owner)->get(route('owner.shops.create'))
            ->assertOk()
            ->assertSee('店舗を登録')
            ->assertSee('action="'.route('owner.shops.store').'"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_owner_can_create_shop_as_draft_owned_by_self(): void
    {
        $this->store($this->validInput())
            ->assertRedirect(route('owner.shops.index'))
            ->assertSessionHas('success', '店舗を登録しました。座席種別と放映を登録してから公開してください。');

        // リダイレクト先の一覧に、メッセージが表示される
        $this->actingAs($this->owner)->followingRedirects()->post(route('owner.shops.store'), $this->validInput(['name' => '2店目']))
            ->assertOk()
            ->assertSee('店舗を登録しました。座席種別と放映を登録してから公開してください。');
        Shop::query()->where('name', '2店目')->delete();

        $shop = Shop::query()->sole();
        $this->assertSame($this->owner->id, (int) $shop->user_id);
        $this->assertSame(Shop::STATUS_DRAFT, $shop->status);
        $this->assertSame('新しい店名', $shop->name);
        // 郵便番号は整形、時刻は H:i:s で保存（編集と同じ）
        $this->assertSame('150-0001', $shop->postal_code);
        $this->assertSame('東京都', $shop->prefecture);
        $this->assertSame('渋谷区', $shop->city);
        $this->assertSame('神宮前1-1-1', $shop->address_line);
        $this->assertSame('03-9999-8888', $shop->phone_number);
        $this->assertSame('新しい説明', $shop->description);
        $this->assertSame('18:00:00', $shop->opening_time);
        $this->assertSame('02:00:00', $shop->closing_time);
        $this->assertSame('https://example.com/bar', $shop->website_url);
        $this->assertNull($shop->latitude);
        $this->assertNull($shop->longitude);
        $this->assertNull($shop->google_place_id);

        // 一覧に出て、編集画面を開ける
        $this->actingAs($this->owner)->get(route('owner.shops.index'))
            ->assertOk()
            ->assertSee('新しい店名')
            ->assertSee('非公開')
            ->assertSee(route('owner.shops.edit', $shop));
        $this->actingAs($this->owner)->get(route('owner.shops.edit', $shop))
            ->assertOk()
            ->assertSee('value="新しい店名"', false);
    }

    public function test_optional_fields_can_be_empty(): void
    {
        $this->store(['name' => '最小の店', 'address_line' => '1-1'])
            ->assertRedirect(route('owner.shops.index'))
            ->assertSessionHasNoErrors();

        $shop = Shop::query()->sole();
        $this->assertNull($shop->postal_code);
        $this->assertNull($shop->prefecture);
        $this->assertNull($shop->opening_time);
        $this->assertNull($shop->website_url);
        $this->assertSame(Shop::STATUS_DRAFT, $shop->status);
    }

    public function test_status_is_set_to_draft_by_the_server_not_by_db_default(): void
    {
        // 保存の直前（DB の既定値が入る前）の属性を捕まえて、サーバー側で draft を入れていることを確かめる
        $statusBeforeInsert = 'not-set';
        Shop::creating(function (Shop $shop) use (&$statusBeforeInsert) {
            $statusBeforeInsert = $shop->getAttributes()['status'] ?? null;
        });

        $this->store($this->validInput(['status' => Shop::STATUS_PUBLISHED]))->assertSessionHasNoErrors();

        $this->assertSame(Shop::STATUS_DRAFT, $statusBeforeInsert);
    }

    public function test_owner_can_create_multiple_shops(): void
    {
        $this->store($this->validInput(['name' => '1店目']))->assertSessionHasNoErrors();
        $this->store($this->validInput(['name' => '2店目']))->assertSessionHasNoErrors();

        $this->assertSame(2, Shop::query()->where('user_id', $this->owner->id)->count());
    }

    public function test_created_shop_is_not_public_until_published(): void
    {
        $this->store($this->validInput());
        $shop = Shop::query()->sole();

        $this->actingAs(User::factory()->create())->get(route('shops.show', $shop))->assertNotFound();
    }

    // ---- 一括代入 ----

    public function test_protected_columns_are_not_used(): void
    {
        $other = User::factory()->shopOwner()->create();

        $this->store($this->validInput([
            'id' => 99999,
            'user_id' => $other->id,
            'status' => Shop::STATUS_PUBLISHED,
            'latitude' => 35.1,
            'longitude' => 139.1,
            'google_place_id' => 'place-xyz',
            'deleted_at' => '2026-01-01 00:00:00',
        ]))->assertRedirect(route('owner.shops.index'))->assertSessionHasNoErrors();

        $shop = Shop::query()->sole();
        $this->assertNotSame(99999, (int) $shop->id);
        $this->assertSame($this->owner->id, (int) $shop->user_id);
        $this->assertSame(Shop::STATUS_DRAFT, $shop->status);
        $this->assertNull($shop->latitude);
        $this->assertNull($shop->longitude);
        $this->assertNull($shop->google_place_id);
        $this->assertNull($shop->deleted_at);
    }

    // ---- 入力の検証 ----

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected(string $field, array $input): void
    {
        $this->actingAs($this->owner)->from(route('owner.shops.create'))
            ->post(route('owner.shops.store'), $this->validInput($input))
            ->assertRedirect(route('owner.shops.create'))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('shops', 0);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function invalidInputs(): array
    {
        return [
            'name empty' => ['name', ['name' => '']],
            'name 101' => ['name', ['name' => str_repeat('あ', 101)]],
            'name array' => ['name', ['name' => ['a']]],
            'address_line empty' => ['address_line', ['address_line' => '']],
            'address_line 256' => ['address_line', ['address_line' => str_repeat('a', 256)]],
            'city 101' => ['city', ['city' => str_repeat('あ', 101)]],
            'description 2001' => ['description', ['description' => str_repeat('あ', 2001)]],
            'postal 6 digits' => ['postal_code', ['postal_code' => '123456']],
            'postal array' => ['postal_code', ['postal_code' => ['123-4567']]],
            'prefecture unknown' => ['prefecture', ['prefecture' => '東京']],
            'prefecture array' => ['prefecture', ['prefecture' => ['東京都']]],
            'phone letters' => ['phone_number', ['phone_number' => '03-abcd']],
            'phone hyphen only' => ['phone_number', ['phone_number' => '---']],
            'phone 31' => ['phone_number', ['phone_number' => str_repeat('1', 31)]],
            'opening only' => ['closing_time', ['opening_time' => '18:00', 'closing_time' => '']],
            'closing only' => ['opening_time', ['opening_time' => '', 'closing_time' => '02:00']],
            'same time' => ['opening_time', ['opening_time' => '18:00', 'closing_time' => '18:00']],
            'time 25:00' => ['opening_time', ['opening_time' => '25:00']],
            'time array' => ['opening_time', ['opening_time' => ['18:00']]],
            'website javascript' => ['website_url', ['website_url' => 'javascript:alert(1)']],
            'website array' => ['website_url', ['website_url' => ['https://example.com']]],
        ];
    }

    public function test_boundary_values_are_accepted(): void
    {
        $this->store($this->validInput([
            'name' => str_repeat('あ', 100),
            'address_line' => str_repeat('い', 255),
            'city' => str_repeat('う', 100),
            'description' => str_repeat('え', 2000),
            'phone_number' => str_repeat('1', 30),
            'opening_time' => '17:00',
            'closing_time' => '01:00',
        ]))->assertRedirect(route('owner.shops.index'))->assertSessionHasNoErrors();

        $shop = Shop::query()->sole();
        $this->assertSame(100, mb_strlen($shop->name));
        $this->assertSame(2000, mb_strlen($shop->description));
        $this->assertSame('17:00〜翌1:00', $shop->businessHoursLabel());
    }

    public function test_validation_error_keeps_old_input_on_create_form(): void
    {
        $this->actingAs($this->owner)->from(route('owner.shops.create'))
            ->post(route('owner.shops.store'), $this->validInput(['name' => '', 'description' => '残る説明', 'prefecture' => '大阪府']));

        $this->actingAs($this->owner)->get(route('owner.shops.create'))
            ->assertSee('店名を入力してください。')
            ->assertSee('残る説明')
            ->assertSee('<option value="大阪府" selected>', false);
    }

    // ---- 入口・XSS ----

    public function test_index_has_create_link_also_when_no_shops(): void
    {
        $this->actingAs($this->owner)->get(route('owner.shops.index'))
            ->assertOk()
            // 0件の案内の中に、登録へのリンクがある
            ->assertSee('店舗がありません。<a href="'.route('owner.shops.create').'"', false);

        $this->store($this->validInput());

        $this->actingAs($this->owner)->get(route('owner.shops.index'))
            ->assertDontSee('店舗がありません。')
            ->assertSee('店舗を登録する')
            ->assertSee('href="'.route('owner.shops.create').'"', false);
    }

    public function test_name_and_description_are_escaped(): void
    {
        $this->store($this->validInput(['name' => '<script>alert(1)</script>', 'description' => '<script>alert(2)</script>']));
        $shop = Shop::query()->sole();

        foreach ([route('owner.shops.index'), route('owner.shops.edit', $shop)] as $url) {
            $this->actingAs($this->owner)->get($url)
                ->assertOk()
                ->assertDontSee('<script>alert', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        }
    }

    // ---- 回数制限 ----

    public function test_eleventh_store_request_by_same_user_gets_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->store($this->validInput())->assertRedirect(route('owner.shops.index'));
        }

        $this->store($this->validInput())
            ->assertStatus(429)
            ->assertSee('アクセスが集中しています。しばらく待ってからもう一度お試しください。');
        $this->assertDatabaseCount('shops', 10);

        // 別のオーナーは影響を受けない
        $other = User::factory()->shopOwner()->create();
        $this->actingAs($other)->post(route('owner.shops.store'), $this->validInput())
            ->assertRedirect(route('owner.shops.index'));

        // 1分たつと戻る
        $this->travel(61)->seconds();
        $this->store($this->validInput())->assertRedirect(route('owner.shops.index'));
    }

    public function test_non_owner_requests_are_counted_before_authorization(): void
    {
        $user = User::factory()->create();

        // throttle は認可より先に動く：10回目までは 403、11回目は 429
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('owner.shops.store'), $this->validInput())->assertForbidden();
        }

        $this->actingAs($user)->post(route('owner.shops.store'), $this->validInput())->assertStatus(429);
        $this->assertDatabaseCount('shops', 0);
    }
}
