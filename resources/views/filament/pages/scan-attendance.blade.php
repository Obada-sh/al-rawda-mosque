<x-filament-panels::page>
    <div
        class="rawda-scan"
        x-data="@include('filament.partials.scanner-desk')"
        x-init="focusBox()"
        x-on:keydown.window="keepFocus($event)"
        x-on:scan-processed.window="beep($event.detail.tone); focusBox()"
    >
        <form wire:submit="scan" class="rawda-scan__desk">
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
                بطاقة الطالب أو بطاقة الأستاذ: وجّه الماسح إلى رمزها، أو اكتب رقمها ثم اضغط Enter.
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
                    @if ($result['teacher'])
                        <p class="rawda-scan__kind">أستاذ</p>
                    @endif
                    <p class="rawda-scan__student">{{ $result['name'] }}</p>
                    @if (filled($result['circle']) || filled($result['course']))
                        <p class="rawda-scan__place">{{ collect([$result['circle'], $result['course']])->filter()->implode(' — ') }}</p>
                    @endif
                @endif

                @if ($refusedCard !== null)
                    <div class="rawda-scan__offer">
                        <x-filament::button color="gray" wire:click="openExceptionalSession">
                            فتح جلسة استثنائية اليوم وتسجيل الحضور
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">آخر البطاقات اليوم</x-slot>
            <x-slot name="description">حضور اليوم في كل الدورات — الطلاب: {{ $attendanceToday }} · الأساتذة: {{ $teachersToday }}</x-slot>

            @if ($recentScans->isEmpty())
                <p class="rawda-scan__empty">لم تُمسح أي بطاقة اليوم.</p>
            @else
                <ul class="rawda-scan__recent">
                    @foreach ($recentScans as $scan)
                        <li wire:key="recent-scan-{{ $scan['key'] }}">
                            <span class="rawda-scan__recent-name">
                                {{ $scan['name'] }}
                                @if ($scan['teacher'])
                                    <span class="rawda-scan__tag">أستاذ</span>
                                @endif
                            </span>
                            <span class="rawda-scan__recent-circle">{{ $scan['place'] }}</span>
                            <span class="rawda-scan__recent-time" dir="ltr">{{ $scan['time']->format('H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
