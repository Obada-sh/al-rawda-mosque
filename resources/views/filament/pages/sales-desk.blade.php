@use('App\Filament\Support\Presenters\MarketPresenter')

<x-filament-panels::page>
    @if ($market === null)
        {{-- Which market the desk sells for. --}}
        <x-filament::section>
            <x-slot name="heading">اختر السوق</x-slot>

            @if ($openMarkets === [])
                <p class="rawda-desk__note">
                    لا يوجد سوق مفتوح الآن. افتح سوقاً لدورة من صفحة
                    <a href="{{ $marketsUrl }}" class="rawda-desk__link">الأسواق</a>
                    وأضف بضاعته، ثم ارجع إلى هنا.
                </p>
            @else
                <div class="rawda-desk__markets">
                    @foreach ($openMarkets as $id => $label)
                        <x-filament::button color="gray" size="lg" wire:click="chooseMarket({{ $id }})" wire:key="market-choice-{{ $id }}">
                            {{ $label }}
                        </x-filament::button>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @else
        <div
            class="rawda-desk"
            x-data="@include('filament.partials.scanner-desk')"
            x-init="focusBox()"
            x-on:keydown.window="keepFocus($event)"
            x-on:scan-processed.window="beep($event.detail.tone); focusBox()"
        >
            <div class="rawda-desk__heading">
                <p class="rawda-desk__market">{{ $market->name }} <span>— {{ $market->course->name }}</span></p>
                @if (count($openMarkets) > 1)
                    <x-filament::link tag="button" wire:click="chooseMarket(null)">تغيير السوق</x-filament::link>
                @endif
            </div>

            {{-- The items: a click puts one in the basket. --}}
            <section class="rawda-desk__items" aria-label="البضاعة">
                @forelse ($items as $item)
                    @php
                        $inBasket = $quantities[$item->getKey()] ?? 0;
                        $picture = MarketPresenter::pictureUrl($item);
                        $stock = MarketPresenter::stock($item);
                    @endphp
                    <button
                        type="button"
                        wire:key="desk-item-{{ $item->getKey() }}"
                        wire:click="add({{ $item->getKey() }})"
                        @disabled(! $item->isOnSale())
                        @class(['rawda-desk__item', 'is-chosen' => $inBasket > 0, 'is-sold-out' => ! $item->isOnSale()])
                    >
                        @if ($inBasket > 0)
                            <span class="rawda-desk__count">{{ $inBasket }}</span>
                        @endif
                        @if ($picture !== null)
                            <img src="{{ $picture }}" alt="" class="rawda-desk__picture">
                        @else
                            <span class="rawda-desk__picture rawda-desk__picture--none">
                                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedGift" class="rawda-desk__picture-icon" />
                            </span>
                        @endif
                        <span class="rawda-desk__name">{{ $item->name }}</span>
                        @if (filled($item->description))
                            <span class="rawda-desk__description">{{ $item->description }}</span>
                        @endif
                        <span class="rawda-desk__price">{{ MarketPresenter::points($item->price) }}</span>
                        @if ($stock !== null)
                            <span class="rawda-desk__stock">{{ $stock }}</span>
                        @endif
                    </button>
                @empty
                    <p class="rawda-desk__note">لا توجد بضاعة معروضة في هذا السوق. أضفها من صفحة السوق.</p>
                @endforelse
            </section>

            <aside class="rawda-desk__side">
                {{-- The basket of the student at the desk. --}}
                <x-filament::section>
                    <x-slot name="heading">السلة</x-slot>

                    @if ($basketLines->isEmpty())
                        <p class="rawda-desk__note">السلة فارغة. اختر البضاعة، أو امسح بطاقة الطالب لمعرفة رصيده.</p>
                    @else
                        <ul class="rawda-desk__basket">
                            @foreach ($basketLines as $line)
                                <li wire:key="basket-{{ $line['item']->getKey() }}">
                                    <span class="rawda-desk__basket-name">{{ $line['item']->name }}</span>
                                    <span class="rawda-desk__stepper">
                                        <button type="button" wire:click="decrease({{ $line['item']->getKey() }})" aria-label="واحدة أقل">−</button>
                                        <span>{{ $line['quantity'] }}</span>
                                        <button type="button" wire:click="add({{ $line['item']->getKey() }})" aria-label="واحدة أخرى">+</button>
                                    </span>
                                    <span class="rawda-desk__basket-points">{{ $line['points'] }}</span>
                                    <button type="button" class="rawda-desk__remove" wire:click="remove({{ $line['item']->getKey() }})" aria-label="إزالة {{ $line['item']->name }}">×</button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="rawda-desk__total">
                            <span>المجموع</span>
                            <strong>{{ MarketPresenter::points($basketTotal) }}</strong>
                        </div>
                        <x-filament::link tag="button" color="gray" wire:click="clearBasket">تفريغ السلة</x-filament::link>
                    @endif
                </x-filament::section>

                {{-- The scanner pays. --}}
                <form wire:submit="sell" class="rawda-scan__desk">
                    <label for="rawda-sale-card" class="rawda-scan__label">
                        {{ $basketLines->isEmpty() ? 'امسح بطاقة الطالب لمعرفة رصيده' : 'امسح بطاقة الطالب للدفع' }}
                    </label>
                    <input
                        id="rawda-sale-card"
                        x-ref="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        dir="ltr"
                        wire:model="cardCode"
                        class="rawda-scan__box"
                    />
                </form>

                @if ($result === null)
                    <div class="rawda-scan__answer rawda-scan__answer--idle rawda-desk__answer">
                        <p class="rawda-scan__message">بانتظار أول طالب</p>
                    </div>
                @else
                    <div
                        class="rawda-scan__answer rawda-scan__answer--{{ $result['tone'] }} rawda-desk__answer"
                        wire:key="sale-answer-{{ $result['sequence'] }}"
                        role="status"
                    >
                        <p class="rawda-scan__message">{{ $result['message'] }}</p>

                        @if (filled($result['name']))
                            @if (filled($result['photo']))
                                <img class="rawda-scan__photo" src="{{ $result['photo'] }}" alt="">
                            @endif
                            <p class="rawda-desk__student">{{ $result['name'] }}</p>
                            @if (filled($result['place']))
                                <p class="rawda-scan__place">{{ $result['place'] }}</p>
                            @endif
                        @endif

                        @if ($result['lines'] !== [])
                            <p class="rawda-desk__bought">{{ implode('، ', $result['lines']) }}</p>
                        @endif

                        @if ($result['spent'] !== null)
                            <p class="rawda-desk__figure">خُصم: <strong>{{ $result['spent'] }}</strong></p>
                        @endif

                        @if ($result['balance'] !== null)
                            <p class="rawda-desk__figure">{{ $result['spent'] === null ? 'الرصيد' : 'الرصيد الباقي' }}: <strong>{{ $result['balance'] }}</strong></p>
                        @endif
                    </div>
                @endif

                <x-filament::section>
                    <x-slot name="heading">آخر المشتريات اليوم</x-slot>

                    @if ($recentSales->isEmpty())
                        <p class="rawda-desk__note">لم يُبع شيء اليوم في هذا السوق.</p>
                    @else
                        <ul class="rawda-scan__recent">
                            @foreach ($recentSales as $sale)
                                <li wire:key="recent-sale-{{ $sale->getKey() }}">
                                    <span class="rawda-scan__recent-name">
                                        {{ $sale->enrollment->student->full_name }}
                                        <span class="rawda-desk__recent-lines">{{ MarketPresenter::lines($sale) }}</span>
                                    </span>
                                    <span class="rawda-scan__recent-circle">{{ $sale->total_points }}</span>
                                    <span class="rawda-scan__recent-time" dir="ltr">{{ $sale->sold_at->format('H:i') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>
            </aside>
        </div>
    @endif
</x-filament-panels::page>
