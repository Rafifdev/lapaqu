<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-chart-bar" style="width: 18px; height: 18px; color: #3b82f6;" />
                    <span style="font-weight: 700; font-size: 0.9375rem;">Uptime Timeline & Service Availability</span>
                </div>
            </div>
        </x-slot>

        <x-slot name="headerEnd">
            <div style="display: flex; align-items: center; gap: 0.375rem;">
                <select 
                    wire:model.live="filter" 
                    class="text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 py-1 px-2.5 shadow-sm focus:ring-primary-500 focus:border-primary-500 font-medium"
                >
                    <option value="24h">24 Jam</option>
                    <option value="7d">7 Hari</option>
                    <option value="30d">30 Hari</option>
                </select>
            </div>
        </x-slot>

        <!-- Compact Timeline List (Tighter vertical padding & 14px sleek bars) -->
        <div style="display: flex; flex-direction: column; gap: 0.625rem; padding-top: 0.25rem;">
            @foreach ($this->getTimelines() as $timeline)
                <div style="padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.02); display: flex; flex-direction: column; gap: 0.375rem;">
                    
                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                        <div style="display: flex; align-items: center; gap: 0.375rem;">
                            <x-filament::icon :icon="$timeline['icon']" style="width: 15px; height: 15px; color: #9ca3af;" />
                            <span style="font-weight: 600; color: #f3f4f6;">{{ $timeline['label'] }}</span>
                            <x-filament::badge :color="$timeline['statusColor']" size="sm">
                                {{ $timeline['status'] }}
                            </x-filament::badge>
                        </div>
                        <div style="font-size: 0.75rem; font-weight: 600; color: #9ca3af;">
                            <span style="color: #10b981; font-weight: 700;">{{ $timeline['uptime'] }}%</span> Uptime
                        </div>
                    </div>

                    <!-- Sleek 14px horizontal bar segments with subtle gap -->
                    <div style="display: flex; align-items: center; gap: 2px; width: 100%; height: 14px;">
                        @foreach ($timeline['segments'] as $seg)
                            <div 
                                style="flex: 1; height: 100%; border-radius: 2px; background-color: {{ $seg['hex'] }}; transition: opacity 0.15s ease;"
                                title="{{ $seg['time'] }} — {{ $seg['summary'] }}"
                                onmouseover="this.style.opacity='0.75'"
                                onmouseout="this.style.opacity='1'"
                            ></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
