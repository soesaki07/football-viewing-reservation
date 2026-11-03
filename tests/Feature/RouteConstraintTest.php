<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteConstraintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nonNumericRoutes(): array
    {
        return [
            'matches.show' => ['get', '/matches/1abc'],
            'shops.show' => ['get', '/shops/1abc'],
            'reservations.create' => ['get', '/broadcasts/1abc/reservations/create'],
            'reservations.confirm' => ['post', '/broadcasts/1abc/reservations/confirm'],
            'reservations.store' => ['post', '/broadcasts/1abc/reservations'],
            'reservations.show' => ['get', '/reservations/1abc'],
            'reservations.cancel' => ['patch', '/reservations/1abc/cancel'],
            'reservations.complete' => ['get', '/reservations/1abc/complete'],
            'owner.shops.edit' => ['get', '/owner/shops/1abc/edit'],
            'owner.shops.update' => ['put', '/owner/shops/1abc'],
            'owner.reservations.show' => ['get', '/owner/reservations/1abc'],
            'owner.reservations.visit-status' => ['patch', '/owner/reservations/1abc/visit-status'],
            'owner.broadcasts.edit' => ['get', '/owner/broadcasts/1abc/edit'],
            'owner.broadcasts.update' => ['put', '/owner/broadcasts/1abc'],
            'owner.seat-types.edit' => ['get', '/owner/seat-types/1abc/edit'],
            'owner.seat-types.update' => ['put', '/owner/seat-types/1abc'],
            'owner.seat-types.active' => ['patch', '/owner/seat-types/1abc/active'],
        ];
    }

    #[DataProvider('nonNumericRoutes')]
    public function test_non_numeric_id_returns_404(string $method, string $uri): void
    {
        $owner = User::factory()->shopOwner()->create();

        $this->actingAs($owner)->call($method, $uri)->assertNotFound();
    }

    public function test_numeric_id_still_reaches_the_controller(): void
    {
        $owner = User::factory()->shopOwner()->create();

        // 存在しない数字の ID はコントローラまで届き、モデルが無いことによる 404 になる
        // （ルートの形で弾かれたのではないことを、数字でも 404 になる経路が別にあることで区別する）
        $this->actingAs($owner)->get('/shops/999999')->assertNotFound();

        // 実在する数字の ID は今までどおり表示できる
        $shop = Shop::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner)->get(route('owner.shops.edit', $shop))->assertOk();
    }

    public function test_every_route_parameter_is_restricted_to_digits(): void
    {
        // 非数字は、モデルが見つからなくても 404 になるため、ルートの制約そのものを確かめる。
        // 新しい名前のパラメータを足して Route::pattern に入れ忘れたときも落ちるよう、全ルートの全パラメータを見る
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            // Laravel 標準のファイル配信（storage.local・storage.local.upload の {path}）は、パスを受け取るため対象外
            if (in_array($route->getName(), ['storage.local', 'storage.local.upload'], true)) {
                continue;
            }

            foreach ($route->parameterNames() as $parameter) {
                $this->assertSame('[0-9]+', $route->wheres[$parameter] ?? null, $route->uri().' の {'.$parameter.'}');
                $checked++;
            }
        }

        $this->assertGreaterThan(15, $checked);
    }
}
