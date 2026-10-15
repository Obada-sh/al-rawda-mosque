<?php

namespace Tests\Feature\Panel;

use App\Actions\Market\SaveMarket;
use App\Actions\Market\SaveMarketItem;
use App\Actions\Market\SellToCard;
use App\Filament\Pages\Market\SalesDesk;
use App\Filament\Resources\Markets\MarketResource;
use App\Filament\Resources\Markets\Pages\EditMarket;
use App\Filament\Resources\Markets\Pages\ListMarkets;
use App\Filament\Resources\Markets\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Markets\RelationManagers\SalesRelationManager;
use App\Filament\Resources\MarketSales\Pages\ListMarketSales;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\PurchasesRelationManager;
use App\Filament\Support\Presenters\MarketPresenter;
use App\Models\Market;
use App\Models\MarketItem;
use App\Models\MarketSale;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SeedsReportWeek;
use Tests\Support\Pictures;

/**
 * The screens of the points market: opening a market, its items, the sales
 * desk, the register of purchases and the student's purchases and balance.
 * On the week of SeedsReportWeek (أحمد 27 points, خالد 9, بلال 6).
 */
class PointsMarketPanelTest extends PanelTestCase
{
    use SeedsReportWeek;

    private Market $market;

    private MarketItem $pen;

    private MarketItem $medal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReportWeek();
        $this->market = app(SaveMarket::class)->handle(['course_id' => $this->course->getKey(), 'name' => 'سوق نهاية الشهر']);
        $this->pen = app(SaveMarketItem::class)->handle($this->market, ['name' => 'قلم', 'price' => 10, 'description' => 'قلم أزرق']);
        $this->medal = app(SaveMarketItem::class)->handle($this->market, ['name' => 'ميدالية', 'price' => 5, 'stock' => 2]);
    }

    private function desk(): mixed
    {
        return Livewire::withQueryParams(['market' => $this->market->getKey()])->test(SalesDesk::class);
    }

    public function test_a_market_is_opened_for_a_course_and_filled_with_its_items(): void
    {
        Livewire::test(ListMarkets::class)
            ->assertCanSeeTableRecords([$this->market])
            ->callAction('open', data: ['course_id' => $this->course->getKey(), 'name' => 'سوق رمضان'])
            ->assertHasNoActionErrors()
            ->assertRedirect(MarketResource::getUrl('edit', ['record' => Market::query()->where('name', 'سوق رمضان')->sole()]));

        Livewire::test(ListMarkets::class)
            ->callAction('open', data: ['course_id' => $this->course->getKey(), 'name' => 'سوق رمضان'])
            ->assertHasActionErrors(['name']);

        $ramadan = Market::query()->where('name', 'سوق رمضان')->sole();
        $on = ['ownerRecord' => $ramadan, 'pageClass' => EditMarket::class];

        Livewire::test(ItemsRelationManager::class, $on)
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'مصحف جيب',
                'price' => '120',
                'stock' => '6',
                'description' => 'بغلاف جلدي',
                'image' => Pictures::png('quran.png'),
            ])
            ->assertHasNoActionErrors();

        $quran = MarketItem::query()->where('name', 'مصحف جيب')->sole();
        $this->assertSame([120, 6, $ramadan->getKey()], [$quran->price, $quran->stock, $quran->market_id]);
        Storage::disk('local')->assertExists((string) $quran->image_path);

        Livewire::test(ItemsRelationManager::class, $on)
            ->assertCanSeeTableRecords([$quran])
            ->assertSee('120 نقطة')
            ->assertSee('بقي 6')
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'كرة', 'price' => '0'])
            ->assertHasActionErrors(['price']);

        // The picture is handed to the signed-in admin only.
        $this->get((string) MarketPresenter::pictureUrl($quran))->assertOk();

        // Closed, the market keeps its items but leaves the desk.
        Livewire::test(EditMarket::class, ['record' => $ramadan->getRouteKey()])
            ->assertActionVisible('desk')
            ->fillForm(['name' => 'سوق رمضان', 'is_open' => false])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionHidden('desk');

        $this->assertFalse($ramadan->fresh()->is_open);
    }

    public function test_the_desk_sells_the_basket_to_the_scanned_card(): void
    {
        $this->desk()
            ->assertSee('سوق نهاية الشهر')
            ->assertSee('قلم أزرق')
            ->call('add', $this->pen->getKey())
            ->call('add', $this->medal->getKey())
            ->call('add', $this->medal->getKey())
            // Only two medals are left: a third one is not put in the basket.
            ->call('add', $this->medal->getKey())
            ->assertSet('basket', [
                ['item' => $this->pen->getKey(), 'quantity' => 1],
                ['item' => $this->medal->getKey(), 'quantity' => 2],
            ])
            ->assertSee('20 نقطة')
            ->set('cardCode', $this->ahmad->card_code)
            ->call('sell')
            ->assertSet('result.tone', 'success')
            ->assertSet('result.name', 'أحمد')
            ->assertSet('result.lines', ['قلم', 'ميدالية ×2'])
            ->assertSet('result.spent', '20 نقطة')
            ->assertSet('result.balance', 7)
            ->assertSet('basket', [])
            ->assertSet('cardCode', '')
            ->assertSee('الرصيد الباقي')
            ->assertSee('آخر المشتريات اليوم');

        $this->assertSame(20, MarketSale::query()->sole()->total_points);
        $this->assertSame(0, $this->medal->fresh()->stock);
    }

    public function test_the_desk_explains_what_it_does_not_sell_and_keeps_the_basket(): void
    {
        $this->desk()
            ->call('add', $this->pen->getKey())
            ->set('cardCode', $this->bilal->card_code)
            ->call('sell')
            ->assertSet('result.tone', 'danger')
            ->assertSet('result.message', 'رصيد الطالب لا يكفي: رصيده 6 والسلة بـ 10 نقاط')
            ->assertSet('result.name', 'بلال')
            ->assertSet('basket', [['item' => $this->pen->getKey(), 'quantity' => 1]])
            ->call('decrease', $this->pen->getKey())
            ->assertSet('basket', [])
            // With an empty basket the card only shows the balance.
            ->set('cardCode', $this->bilal->card_code)
            ->call('sell')
            ->assertSet('result.tone', 'warning')
            ->assertSet('result.balance', 6)
            ->set('cardCode', '123')
            ->call('sell')
            ->assertSet('result.message', 'بطاقة غير معروفة');

        $this->assertSame(0, MarketSale::query()->count());
    }

    public function test_the_desk_offers_the_open_markets_only(): void
    {
        $other = Market::factory()->create(['course_id' => $this->course->getKey(), 'name' => 'سوق الربيع']);
        $closed = Market::factory()->closed()->create(['course_id' => $this->course->getKey(), 'name' => 'سوق قديم']);

        // Two open markets: the admin chooses.
        Livewire::test(SalesDesk::class)
            ->assertSet('marketId', null)
            ->assertSee('اختر السوق')
            ->assertSee('سوق الربيع')
            ->assertDontSee('سوق قديم')
            ->call('chooseMarket', $other->getKey())
            ->assertSet('marketId', $other->getKey())
            ->call('chooseMarket', $closed->getKey())
            ->assertSet('marketId', null);

        Livewire::withQueryParams(['market' => $closed->getKey()])->test(SalesDesk::class)->assertSet('marketId', null);

        // One open market: it is chosen already.
        $other->update(['is_open' => false]);
        Livewire::test(SalesDesk::class)->assertSet('marketId', $this->market->getKey());
    }

    public function test_purchases_are_listed_and_a_mistake_is_cancelled(): void
    {
        app(SellToCard::class)->handle([
            'market_id' => $this->market->getKey(),
            'card_code' => $this->ahmad->card_code,
            'items' => [['item_id' => $this->pen->getKey(), 'quantity' => 2]],
        ]);
        $sale = MarketSale::query()->sole();

        Livewire::test(ListMarketSales::class)
            ->assertCanSeeTableRecords([$sale])
            ->assertSee('قلم ×2')
            ->assertSee('أحمد');

        Livewire::test(SalesRelationManager::class, ['ownerRecord' => $this->market, 'pageClass' => EditMarket::class])
            ->assertCanSeeTableRecords([$sale]);

        $student = ['ownerRecord' => $this->ahmad->student, 'pageClass' => ViewStudent::class];

        Livewire::test(PurchasesRelationManager::class, $student)
            ->assertCanSeeTableRecords([$sale]);

        // The courses tab: what he earned, and what is left to spend.
        Livewire::test(EnrollmentsRelationManager::class, $student)
            ->assertTableColumnStateSet('point_transactions_sum_points', 27, $this->ahmad)
            ->assertTableColumnStateSet('market_balance', 7, $this->ahmad);

        Livewire::test(ListMarketSales::class)
            ->callAction(TestAction::make('cancelSale')->table($sale))
            ->assertNotified('أُلغي الشراء ورجعت النقاط إلى الطالب');

        $this->assertSame(0, MarketSale::query()->count());

        Livewire::test(EnrollmentsRelationManager::class, $student)
            ->assertTableColumnStateSet('market_balance', 27, $this->ahmad);
    }

    public function test_the_market_screens_open(): void
    {
        foreach ([MarketResource::getUrl(), MarketResource::getUrl('edit', ['record' => $this->market]), SalesDesk::getUrl(['market' => $this->market->getKey()]), ListMarketSales::getUrl()] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
