<x-filament-panels::page>
    <div
        class="rawda-scan"
        x-data="@include('filament.partials.scanner-desk')"
        x-init="focusBox()"
        x-on:keydown.window="keepFocus($event)"
        x-on:scan-processed.window="beep($event.detail.tone); focusBox()"
        x-on:scan-day-changed.window="$nextTick(() => focusBox())"
    >
        @php($anotherDay = $this->anotherDay())

        <div class="rawda-scan__day">
            {{ $this->dayForm }}
        </div>

        @if ($anotherDay !== null)
            <div class="rawda-scan__another-day" role="alert">
                <p>تُسجَّل البطاقات الآن ليوم <strong>{{ $anotherDay }}</strong>، لا لليوم.</p>
                <x-filament::button color="warning" size="sm" wire:click="backToToday">العودة إلى اليوم</x-filament::button>
            </div>
        @endif

        <form wire:submit="scan" @class(['rawda-scan__desk', 'is-another-day' => $anotherDay !== null])>
            <label for="rawda-card-code" class="rawda-scan__label">امسح البطاقة</label>

            <input
                id="rawda-card-code"
                x-ref="code"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                dir="ltr"
                wire:model="cardCode"
                class="rawda-scan__box"
                aria-describedby="rawda-card-hint"
            />

            <p id="rawda-card-hint" class="rawda-scan__hint">
                بطاقة طالب الانجليزي: وجّه الماسح إلى رمزها، أو اكتب رقمها ثم اضغط Enter.
            </p>
        </form>

        @if ($result === null)
            <div class="rawda-scan__answer rawda-scan__answer--idle">
                <p class="rawda-scan__message">بانتظار أول بطاقة</p>
            </div>
        @else
            <div
                class="rawda-scan__answer rawda-scan__answer--{{ $result['tone'] }}"
                wire:key="scan-answer-{{ $result['sequence'] }}"
                role="status"
            >
                <p class="rawda-scan__message">{{ $result['message'] }}</p>

                @if (filled($result['name']))
                    @if (filled($result['photo']))
                        <img class="rawda-scan__photo" src="{{ $result['photo'] }}" alt="">
                    @endif
                    <p class="rawda-scan__student">{{ $result['name'] }}</p>
                    @if (filled($result['hall']) || filled($result['course']))
                        <p class="rawda-scan__place">{{ collect([$result['hall'], $result['course']])->filter()->implode(' — ') }}</p>
                    @endif
                @endif

                @if ($refusedCard !== null)
                    <div class="rawda-scan__offer">
                        <x-filament::button color="gray" wire:click="openExceptionalSession">
                            {{ $this->exceptionalSessionLabel() }}
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">آخر البطاقات اليوم</x-slot>
            <x-slot name="description">حضور اليوم في كل دورات الانجليزي: {{ $attendanceToday }}</x-slot>

            @if ($recentScans->isEmpty())
                <p class="rawda-scan__empty">لم تُمسح أي بطاقة اليوم.</p>
            @else
                <ul class="rawda-scan__recent">
                    @foreach ($recentScans as $scan)
                        <li wire:key="recent-scan-{{ $scan['key'] }}">
                            <span class="rawda-scan__recent-name">{{ $scan['name'] }}</span>
                            <span class="rawda-scan__recent-circle">
                                {{ $scan['place'] }}
                                @if ($scan['day'] !== null)
                                    <span class="rawda-scan__tag">ليوم {{ $scan['day'] }}</span>
                                @endif
                            </span>
                            <span class="rawda-scan__recent-time" dir="ltr">{{ $scan['time']->format('H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
