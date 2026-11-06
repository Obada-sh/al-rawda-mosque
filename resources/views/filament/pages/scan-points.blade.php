{{--
    The points desk ($result: what the last scanned card showed, see
    ScanPoints): one box the scanner types into, and a large answer with
    the student's points in the card's course.
--}}
<x-filament-panels::page>
    <div
        class="rawda-scan"
        x-data="@include('filament.partials.scanner-desk')"
        x-init="focusBox()"
        x-on:keydown.window="keepFocus($event)"
        x-on:scan-processed.window="beep($event.detail.tone); focusBox()"
    >
        <form wire:submit="lookUp" class="rawda-scan__desk">
            <label for="rawda-points-card" class="rawda-scan__label">امسح بطاقة الطالب لمعرفة نقاطه</label>

            <input
                id="rawda-points-card"
                x-ref="code"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                dir="ltr"
                wire:model="cardCode"
                class="rawda-scan__box"
                aria-describedby="rawda-points-hint"
            />

            <p id="rawda-points-hint" class="rawda-scan__hint">
                وجّه الماسح إلى رمز البطاقة، أو اكتب رقمها ثم اضغط Enter. النقاط هي نقاطه في دورة البطاقة، ولا يُسجَّل شيء.
            </p>
        </form>

        @if ($result === null)
            <div class="rawda-scan__answer rawda-scan__answer--idle">
                <p class="rawda-scan__message">بانتظار أول بطاقة</p>
            </div>
        @else
            <div
                class="rawda-scan__answer rawda-scan__answer--{{ $result['tone'] }}"
                wire:key="points-answer-{{ $result['sequence'] }}"
                role="status"
            >
                <p class="rawda-scan__message">{{ $result['message'] }}</p>

                @if (filled($result['name']))
                    @if (filled($result['photo']))
                        <img class="rawda-scan__photo" src="{{ $result['photo'] }}" alt="">
                    @endif
                    <p class="rawda-scan__student">{{ $result['name'] }}</p>
                    @if (filled($result['place']))
                        <p class="rawda-scan__place">{{ $result['place'] }}</p>
                    @endif
                    @if (filled($result['notice']))
                        <p class="rawda-points__notice">{{ $result['notice'] }}</p>
                    @endif
                @endif

                @if ($result['available'] !== null)
                    <p class="rawda-points__balance">
                        <span class="rawda-points__balance-label">الرصيد</span>
                        <strong class="rawda-points__balance-value" dir="ltr">{{ $result['available'] }}</strong>
                        <span class="rawda-points__balance-label">{{ $result['unit'] }}</span>
                    </p>

                    <dl class="rawda-points__figures">
                        <div>
                            <dt>كسب في الدورة</dt>
                            <dd dir="ltr">{{ $result['earned'] }}</dd>
                        </div>
                        <div>
                            <dt>صرف في السوق</dt>
                            <dd dir="ltr">{{ $result['spent'] }}</dd>
                        </div>
                    </dl>

                    @if ($result['sources'] !== [])
                        <ul class="rawda-points__sources" aria-label="مصادر النقاط">
                            @foreach ($result['sources'] as $source)
                                <li>
                                    <span>{{ $source['label'] }}</span>
                                    <strong dir="ltr">{{ $source['points'] }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="rawda-scan__empty">لم يكسب الطالب نقاطاً في هذه الدورة بعد.</p>
                    @endif
                @endif
            </div>

            @if ($result['latest'] !== [])
                <x-filament::section>
                    <x-slot name="heading">آخر نقاط الطالب</x-slot>

                    <ul class="rawda-scan__recent">
                        @foreach ($result['latest'] as $line)
                            <li wire:key="points-line-{{ $result['sequence'] }}-{{ $loop->index }}">
                                <span class="rawda-scan__recent-name">{{ $line['what'] }}</span>
                                <span class="rawda-scan__recent-circle" dir="ltr">{{ $line['day'] }}</span>
                                <strong @class(['rawda-points__line', 'is-penalty' => $line['penalty']]) dir="ltr">{{ $line['points'] }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
