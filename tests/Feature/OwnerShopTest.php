<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastSeatType;
use App\Models\Reservation;
use App\Models\SeatType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFootballMatches;
use Tests\TestCase;

class OwnerShopTest extends TestCase
{
    use CreatesFootballMatches;
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02 12:00:00');

        $this->owner = User::factory()->shopOwner()->create();
        $this->shop = Shop::factory()->create([
            'user_id' => $this->owner->id,
            'name' => 'バーA',
            'postal_code' => '150-0001',
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'address_line' => '神宮前1-1-1',
            'latitude' => 35.67,
            'longitude' => 139.70,
            'google_place_id' => 'place-abc',
            'phone_number' => '03-1234-5678',
            'description' => '元の説明',
            'opening_time' => '17:00:00',
            'closing_time' => '23:00:00',
            'website_url' => null,
            'status' => Shop::STATUS_PUBLISHED,
        ]);
    }

    private function otherShop(): Shop
    {
        return Shop::factory()->create([
            'user_id' => User::factory()->shopOwner()->create()->id,
            'name' => 'バーB',
            'status' => Shop::STATUS_PUBLISHED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => '新しい店名',
            'postal_code' => '150-0001',
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'address_line' => '神宮前1-1-1',
            'phone_number' => '03-9999-8888',
            'description' => '新しい説明',
            'opening_time' => '18:00',
            'closing_time' => '02:00',
            'website_url' => 'https://example.com/bar',
            'status' => Shop::STATUS_PUBLISHED,
        ], $overrides);
    }

    private function update(array $input, ?Shop $shop = null)
    {
        return $this->actingAs($this->owner)->put(route('owner.shops.update', $shop ?? $this->shop), $input);
    }

    // ---- 認可 ----

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('owner.shops.index'))->assertRedirect(route('login'));
        $this->get(route('owner.shops.edit', $this->shop))->assertRedirect(route('login'));
        $this->put(route('owner.shops.update', $this->shop), $this->validInput())->assertRedirect(route('login'));

        $this->assertSame('バーA', $this->shop->fresh()->name);
    }

    public function test_user_and_admin_get_403(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();

        foreach ([User::factory()->create(), $admin] as $user) {
            $this->actingAs($user)->get(route('owner.shops.index'))->assertForbidden();
            $this->actingAs($user)->get(route('owner.shops.edit', $this->shop))->assertForbidden();
            $this->actingAs($user)->put(route('owner.shops.update', $this->shop), $this->validInput())->assertForbidden();
        }

        $this->assertSame('バーA', $this->shop->fresh()->name);
    }

    public function test_other_owners_shop_returns_404_and_is_not_changed(): void
    {
        $other = $this->otherShop();

        $this->actingAs($this->owner)->get(route('owner.shops.edit', $other))->assertNotFound();
        $this->update($this->validInput(['name' => '乗っ取り']), $other)->assertNotFound();
        // 入力が不正でも、認可が先なので 404（他店の存在を検証エラーで漏らさない）
        $this->update(['name' => ''], $other)->assertNotFound();

        $this->assertSame('バーB', $other->fresh()->name);
    }

    public function test_other_owner_cannot_update_with_forbidden_input_even_if_user_role_is_owner(): void
    {
        // shop_owner だが別のオーナーの店舗は 404、shop_owner でないユーザーは入力が不正でも 403
        $other = $this->otherShop();
        $this->actingAs(User::factory()->create())->put(route('owner.shops.update', $other), ['name' => ''])->assertForbidden();
    }

    public function test_deleted_and_missing_shops_return_404(): void
    {
        $this->shop->delete();

        $this->actingAs($this->owner)->get(route('owner.shops.edit', $this->shop->id))->assertNotFound();
        $this->actingAs($this->owner)->put(route('owner.shops.update', $this->shop->id), $this->validInput())->assertNotFound();
        $this->actingAs($this->owner)->get(route('owner.shops.edit', 999999))->assertNotFound();
        $this->actingAs($this->owner)->put(route('owner.shops.update', 999999), $this->validInput())->assertNotFound();

        $this->assertSame('バーA', Shop::withTrashed()->find($this->shop->id)->name);
    }

    public function test_non_numeric_shop_id_returns_404_and_is_not_changed(): void
    {
        // MySQL では「1abc」が数値の 1 として比べられることがあるため、自分の店舗の ID の後ろに文字を付ける
        $id = $this->shop->id.'abc';

        $this->actingAs($this->owner)->get('/owner/shops/'.$id.'/edit')->assertNotFound();
        $this->actingAs($this->owner)->put('/owner/shops/'.$id, $this->validInput())->assertNotFound();

        $this->assertSame('バーA', $this->shop->fresh()->name);
    }

    // ---- 一覧 ----

    public function test_index_shows_only_own_shops_in_id_order_with_details(): void
    {
        $second = Shop::factory()->create([
            'user_id' => $this->owner->id,
            'name' => '二号店',
            'postal_code' => null,
            'prefecture' => '大阪府',
            'city' => null,
            'address_line' => '梅田2-2',
            'phone_number' => null,
            'opening_time' => null,
            'closing_time' => null,
            'status' => Shop::STATUS_DRAFT,
        ]);
        $this->otherShop();
        Shop::factory()->create(['user_id' => $this->owner->id, 'name' => '削除済みの店'])->delete();

        $this->actingAs($this->owner)->get(route('owner.shops.index'))
            ->assertOk()
            ->assertSeeInOrder(['バーA', '〒150-0001', '東京都渋谷区神宮前1-1-1', '03-1234-5678', '17:00〜23:00', '>公開</span>', route('owner.shops.edit', $this->shop), '二号店', '大阪府梅田2-2', '>非公開</span>', route('owner.shops.edit', $second)], false)
            ->assertDontSee('バーB')
            ->assertDontSee('削除済みの店');
    }

    public function test_index_shows_message_when_no_shops(): void
    {
        $owner = User::factory()->shopOwner()->create();

        $this->actingAs($owner)->get(route('owner.shops.index'))->assertOk()->assertSee('店舗がありません。');
    }

    public function test_edit_shows_current_values_and_confirm_for_published_shop(): void
    {
        $response = $this->actingAs($this->owner)->get(route('owner.shops.edit', $this->shop));

        $response->assertOk()
            ->assertSee('value="バーA"', false)
            ->assertSee('value="17:00"', false)
            ->assertSee('value="23:00"', false)
            ->assertSee('data-confirm-field="status"', false)
            ->assertSee('<option value="published" selected>公開</option>', false)
            ->assertDontSee('<option value="draft" selected>', false);
    }

    public function test_edit_has_no_confirm_for_draft_shop(): void
    {
        $this->shop->update(['status' => Shop::STATUS_DRAFT]);

        $this->actingAs($this->owner)->get(route('owner.shops.edit', $this->shop))
            ->assertOk()
            ->assertDontSee('data-confirm', false)
            ->assertSee('<option value="draft" selected>非公開</option>', false)
            ->assertDontSee('<option value="published" selected>', false);
    }

    // ---- 更新 ----

    public function test_owner_can_update_shop(): void
    {
        $this->update($this->validInput())
            ->assertRedirect(route('owner.shops.index'))
            ->assertSessionHas('success', '店舗情報を更新しました。');

        $shop = $this->shop->fresh();
        $this->assertSame('新しい店名', $shop->name);
        $this->assertSame('150-0001', $shop->postal_code);
        $this->assertSame('東京都', $shop->prefecture);
        $this->assertSame('渋谷区', $shop->city);
        $this->assertSame('神宮前1-1-1', $shop->address_line);
        $this->assertSame('03-9999-8888', $shop->phone_number);
        $this->assertSame('新しい説明', $shop->description);
        // 保存は H:i:s の形
        $this->assertSame('18:00:00', $shop->opening_time);
        $this->assertSame('02:00:00', $shop->closing_time);
        $this->assertSame('https://example.com/bar', $shop->website_url);
        $this->assertSame(Shop::STATUS_PUBLISHED, $shop->status);
        $this->assertSame('18:00〜翌2:00', $shop->businessHoursLabel());
    }

    public function test_optional_fields_can_be_empty_and_whitespace_is_trimmed(): void
    {
        $this->update([
            'name' => '  空白あり  ',
            'postal_code' => '',
            'prefecture' => '',
            'city' => '',
            'address_line' => ' 番地 ',
            'phone_number' => '',
            'description' => '   ',
            'opening_time' => '',
            'closing_time' => '',
            'website_url' => '',
            'status' => Shop::STATUS_DRAFT,
        ])->assertRedirect(route('owner.shops.index'));

        $shop = $this->shop->fresh();
        $this->assertSame('空白あり', $shop->name);
        $this->assertSame('番地', $shop->address_line);
        $this->assertNull($shop->postal_code);
        $this->assertNull($shop->prefecture);
        $this->assertNull($shop->city);
        $this->assertNull($shop->phone_number);
        $this->assertNull($shop->description);
        $this->assertNull($shop->opening_time);
        $this->assertNull($shop->closing_time);
        $this->assertNull($shop->website_url);
        $this->assertSame(Shop::STATUS_DRAFT, $shop->status);
    }

    public function test_shop_can_be_published_without_seat_types_or_broadcasts(): void
    {
        $this->shop->update(['status' => Shop::STATUS_DRAFT]);

        $this->update($this->validInput(['status' => Shop::STATUS_PUBLISHED]))->assertRedirect(route('owner.shops.index'));

        $this->assertSame(Shop::STATUS_PUBLISHED, $this->shop->fresh()->status);
    }

    public function test_protected_columns_are_not_changed_by_input(): void
    {
        $other = $this->otherShop();

        $this->update($this->validInput([
            'user_id' => $other->user_id,
            'latitude' => '1.5',
            'longitude' => '2.5',
            'google_place_id' => 'hacked',
            'deleted_at' => '2026-01-01 00:00:00',
            'id' => 12345,
        ]))->assertRedirect(route('owner.shops.index'));

        $shop = $this->shop->fresh();
        $this->assertSame($this->owner->id, (int) $shop->user_id);
        $this->assertEqualsWithDelta(35.67, (float) $shop->latitude, 0.0001);
        $this->assertEqualsWithDelta(139.70, (float) $shop->longitude, 0.0001);
        $this->assertSame('place-abc', $shop->google_place_id);
        $this->assertNull($shop->deleted_at);
        $this->assertSame($this->shop->id, $shop->id);
    }

    public function test_user_id_is_not_mass_assignable(): void
    {
        $this->assertNotContains('user_id', (new Shop)->getFillable());
    }

    // ---- 位置情報 ----

    #[DataProvider('addressChanges')]
    public function test_changing_address_clears_location(array $change): void
    {
        $this->update($this->validInput($change))->assertRedirect(route('owner.shops.index'));

        $shop = $this->shop->fresh();
        $this->assertNull($shop->latitude);
        $this->assertNull($shop->longitude);
        $this->assertNull($shop->google_place_id);
        // 位置情報がないので、地図は住所から出す
        $this->assertStringContainsString(rawurlencode($shop->fullAddress()), $shop->googleMapsEmbedUrl('key'));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function addressChanges(): array
    {
        return [
            'postal_code' => [['postal_code' => '1500002']],
            'prefecture' => [['prefecture' => '大阪府']],
            'city' => [['city' => '新宿区']],
            'address_line' => [['address_line' => '神宮前9-9-9']],
        ];
    }

    public function test_changing_other_fields_keeps_location(): void
    {
        // 郵便番号は「1500001」でも「150-0001」にそろうので、住所の変更にはならない
        $this->update($this->validInput(['postal_code' => '1500001', 'name' => '名前だけ変更']))->assertRedirect(route('owner.shops.index'));

        $shop = $this->shop->fresh();
        $this->assertSame('名前だけ変更', $shop->name);
        $this->assertEqualsWithDelta(35.67, (float) $shop->latitude, 0.0001);
        $this->assertEqualsWithDelta(139.70, (float) $shop->longitude, 0.0001);
        $this->assertSame('place-abc', $shop->google_place_id);
    }

    public function test_hyphenless_postal_code_keeps_location_when_address_is_unchanged(): void
    {
        // Factory（Seeder）はハイフンなしの郵便番号で作る
        $this->shop->update(['postal_code' => '1500001']);

        $this->actingAs($this->owner)->get(route('owner.shops.edit', $this->shop))
            ->assertSee('value="150-0001"', false);

        $this->update($this->validInput(['name' => '名前だけ変更']))->assertRedirect(route('owner.shops.index'));

        $shop = $this->shop->fresh();
        $this->assertSame('150-0001', $shop->postal_code);
        $this->assertEqualsWithDelta(35.67, (float) $shop->latitude, 0.0001);
        $this->assertSame('place-abc', $shop->google_place_id);
    }

    public function test_hyphenless_postal_code_change_clears_location(): void
    {
        $this->shop->update(['postal_code' => '1500001']);

        $this->update($this->validInput(['postal_code' => '1500002']))->assertRedirect(route('owner.shops.index'));

        $shop = $this->shop->fresh();
        $this->assertSame('150-0002', $shop->postal_code);
        $this->assertNull($shop->latitude);
        $this->assertNull($shop->google_place_id);
    }

    // ---- 入力の検証 ----

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected(string $field, array $input): void
    {
        $this->actingAs($this->owner)->from(route('owner.shops.edit', $this->shop))
            ->put(route('owner.shops.update', $this->shop), $this->validInput($input))
            ->assertRedirect(route('owner.shops.edit', $this->shop))
            ->assertSessionHasErrors($field);

        $this->assertSame('バーA', $this->shop->fresh()->name);
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
            'address_line array' => ['address_line', ['address_line' => ['a']]],
            'city 101' => ['city', ['city' => str_repeat('あ', 101)]],
            'city array' => ['city', ['city' => ['a']]],
            'description 2001' => ['description', ['description' => str_repeat('あ', 2001)]],
            'description array' => ['description', ['description' => ['a']]],
            'postal 2-5 digits' => ['postal_code', ['postal_code' => '12-34567']],
            'postal 6 digits' => ['postal_code', ['postal_code' => '123456']],
            'postal letters' => ['postal_code', ['postal_code' => 'abc-defg']],
            'postal array' => ['postal_code', ['postal_code' => ['123-4567']]],
            'prefecture unknown' => ['prefecture', ['prefecture' => '東京']],
            'prefecture array' => ['prefecture', ['prefecture' => ['東京都']]],
            'phone letters' => ['phone_number', ['phone_number' => '03-abcd']],
            'phone space' => ['phone_number', ['phone_number' => '03 1234 5678']],
            'phone hyphen only' => ['phone_number', ['phone_number' => '---']],
            'phone 31' => ['phone_number', ['phone_number' => str_repeat('1', 31)]],
            'phone array' => ['phone_number', ['phone_number' => ['03']]],
            'opening only' => ['closing_time', ['opening_time' => '18:00', 'closing_time' => '']],
            'closing only' => ['opening_time', ['opening_time' => '', 'closing_time' => '02:00']],
            'same time' => ['opening_time', ['opening_time' => '18:00', 'closing_time' => '18:00']],
            'time 25:00' => ['opening_time', ['opening_time' => '25:00']],
            'time text' => ['closing_time', ['closing_time' => 'abc']],
            'time array' => ['opening_time', ['opening_time' => ['18:00']]],
            'website javascript' => ['website_url', ['website_url' => 'javascript:alert(1)']],
            'website ftp' => ['website_url', ['website_url' => 'ftp://example.com']],
            'website no scheme' => ['website_url', ['website_url' => 'example.com']],
            'website 2049' => ['website_url', ['website_url' => 'https://example.com/'.str_repeat('a', 2049 - 20)]],
            'website array' => ['website_url', ['website_url' => ['https://example.com']]],
            'status empty' => ['status', ['status' => '']],
            'status other' => ['status', ['status' => 'closed']],
            'status array' => ['status', ['status' => ['published']]],
        ];
    }

    public function test_boundary_values_are_accepted(): void
    {
        $url = 'https://example.com/'.str_repeat('a', 2048 - 20);
        $this->assertSame(2048, strlen($url));

        $this->update($this->validInput([
            'name' => str_repeat('あ', 100),
            'address_line' => str_repeat('い', 255),
            'city' => str_repeat('う', 100),
            'description' => str_repeat('え', 2000),
            'website_url' => $url,
            'phone_number' => str_repeat('1', 30),
            // 日をまたぐ営業時間
            'opening_time' => '17:00',
            'closing_time' => '01:00',
        ]))->assertRedirect(route('owner.shops.index'))->assertSessionHasNoErrors();

        $shop = $this->shop->fresh();
        $this->assertSame(100, mb_strlen($shop->name));
        $this->assertSame(255, mb_strlen($shop->address_line));
        $this->assertSame(100, mb_strlen($shop->city));
        $this->assertSame(2000, mb_strlen($shop->description));
        $this->assertSame($url, $shop->website_url);
        $this->assertSame('17:00〜翌1:00', $shop->businessHoursLabel());
    }

    public function test_postal_code_is_normalized(): void
    {
        $this->update($this->validInput(['postal_code' => '1234567']))->assertSessionHasNoErrors();
        $this->assertSame('123-4567', $this->shop->fresh()->postal_code);

        $this->update($this->validInput(['postal_code' => '765-4321']))->assertSessionHasNoErrors();
        $this->assertSame('765-4321', $this->shop->fresh()->postal_code);
    }

    public function test_all_prefectures_are_accepted(): void
    {
        $this->assertCount(47, Shop::PREFECTURES);
        $this->assertCount(47, array_unique(Shop::PREFECTURES));

        foreach (Shop::PREFECTURES as $prefecture) {
            $this->update($this->validInput(['prefecture' => $prefecture]))->assertSessionHasNoErrors();
            $this->assertSame($prefecture, $this->shop->fresh()->prefecture);
        }
    }

    public function test_validation_error_keeps_old_input_on_edit_form(): void
    {
        $this->actingAs($this->owner)->from(route('owner.shops.edit', $this->shop))
            ->put(route('owner.shops.update', $this->shop), $this->validInput(['name' => '', 'description' => '残る説明', 'prefecture' => '大阪府']));

        $this->actingAs($this->owner)->get(route('owner.shops.edit', $this->shop))
            ->assertSee('店名を入力してください。')
            ->assertSee('残る説明')
            ->assertSee('<option value="大阪府" selected>', false);
    }

    // ---- 非公開の扱い ----

    public function test_unpublishing_hides_shop_and_stops_new_reservations_but_keeps_existing(): void
    {
        $match = $this->createFootballMatch();
        $broadcast = Broadcast::factory()->forMatch($match)->create([
            'shop_id' => $this->shop->id,
            'status' => Broadcast::STATUS_PUBLISHED,
            'reservation_start_at' => now()->subDay(),
            'reservation_end_at' => now()->addDay(),
        ]);
        $seatType = SeatType::factory()->create(['shop_id' => $this->shop->id, 'name' => '席', 'default_capacity' => 10, 'default_price' => 3000, 'is_active' => true]);
        $seat = BroadcastSeatType::factory()->forSeatType($seatType)->create(['broadcast_id' => $broadcast->id]);
        $customer = User::factory()->create();
        $reservation = Reservation::factory()->state([
            'broadcast_seat_type_id' => $seat->id,
            'number_of_people' => 2,
            'user_id' => $customer->id,
        ])->create();
        $newCustomer = User::factory()->create();

        // 公開中は見える・予約できる
        $this->actingAs($customer)->get(route('shops.show', $this->shop))->assertOk();
        $this->actingAs($customer)->get(route('matches.show', $match->id))->assertSee('バーA');
        $this->actingAs($newCustomer)->get(route('reservations.create', $broadcast))->assertOk();

        $this->update($this->validInput(['name' => 'バーA', 'status' => Shop::STATUS_DRAFT]))->assertRedirect(route('owner.shops.index'));
        $this->assertSame(Shop::STATUS_DRAFT, $this->shop->fresh()->status);

        // 店舗詳細は 404、試合詳細の放映店舗一覧から消える
        $this->actingAs($customer)->get(route('shops.show', $this->shop))->assertNotFound();
        $this->actingAs($customer)->get(route('matches.show', $match->id))->assertDontSee('バーA');
        // 新しい予約はできない（入力・確認・確定）
        $input = ['broadcast_seat_type_id' => $seat->id, 'number_of_people' => 1, 'unit_price' => $seat->price];
        $this->actingAs($newCustomer)->get(route('reservations.create', $broadcast))
            ->assertRedirect(route('matches.show', $match->id))
            ->assertSessionHas('error', 'この放映は現在予約できません。');
        $this->actingAs($newCustomer)->post(route('reservations.confirm', $broadcast), $input)
            ->assertRedirect(route('matches.show', $match->id))
            ->assertSessionHas('error', 'この放映は現在予約できません。');
        // 確認画面を通らずセッションに入力値を直接入れても、確定時に拒否される
        $this->actingAs($newCustomer)
            ->withSession(['reservation_input.'.$broadcast->id => $input])
            ->post(route('reservations.store', $broadcast))
            ->assertRedirect(route('reservations.create', $broadcast))
            ->assertSessionHas('error', 'この放映は現在予約できません。');
        $this->assertSame(1, Reservation::count());

        // 既存の予約は有効のまま、マイページ・予約詳細で見られる
        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
        $this->actingAs($customer)->get(route('mypage'))->assertOk()->assertSee($reservation->reservation_code);
        $this->actingAs($customer)->get(route('reservations.show', $reservation))->assertOk()->assertSee($reservation->reservation_code);
        // オーナーの管理画面は使える
        $this->actingAs($this->owner)->get(route('owner.broadcasts.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('owner.seat-types.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('owner.reservations.index'))->assertOk();

        // 公開に戻すと見える
        $this->update($this->validInput(['name' => 'バーA', 'status' => Shop::STATUS_PUBLISHED]))->assertRedirect(route('owner.shops.index'));
        $this->actingAs($customer)->get(route('shops.show', $this->shop))->assertOk();
        $this->actingAs($customer)->get(route('matches.show', $match->id))->assertSee('バーA');
        $this->actingAs($newCustomer)->get(route('reservations.create', $broadcast))->assertOk();
    }

    // ---- XSS ----

    public function test_name_and_description_are_escaped(): void
    {
        $this->update($this->validInput(['name' => '<script>alert(1)</script>', 'description' => '<script>alert(2)</script>']))
            ->assertRedirect(route('owner.shops.index'));

        foreach ([route('owner.shops.index'), route('owner.shops.edit', $this->shop), route('shops.show', $this->shop)] as $url) {
            $this->actingAs($this->owner)->get($url)
                ->assertOk()
                ->assertDontSee('<script>alert', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        }
    }

    // ---- ヘッダー ----

    public function test_header_shows_link_only_for_shop_owner(): void
    {
        $this->actingAs($this->owner)->get(route('owner.shops.index'))
            ->assertSee('href="'.route('owner.shops.index').'"', false)
            ->assertSee('店舗情報');

        $this->actingAs(User::factory()->create())->get(route('mypage'))
            ->assertDontSee('href="'.route('owner.shops.index').'"', false);
    }
}
