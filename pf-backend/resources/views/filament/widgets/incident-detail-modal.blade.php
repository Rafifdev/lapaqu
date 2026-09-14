<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800">
        <div>
            <div class="text-xs text-gray-500 font-medium">Komponen</div>
            <div class="font-bold text-gray-900 dark:text-gray-100">{{ $record->check_label }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 font-medium">Waktu Kejadian</div>
            <div class="font-semibold text-gray-800 dark:text-gray-200">{{ \Carbon\Carbon::parse($record->created_at)->translatedFormat('d F Y, H:i:s') }}</div>
        </div>
    </div>

    <div>
        <div class="text-xs font-semibold text-gray-500 mb-1">Pesan Error Lengkap:</div>
        <div class="p-3 rounded-lg bg-red-50/50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/40 text-red-700 dark:text-red-300 font-mono text-xs leading-relaxed">
            {{ $record->notification_message ?: $record->short_summary ?: 'Tidak ada pesan detail tambahan.' }}
        </div>
    </div>

    @if (!empty($record->meta))
        <div>
            <div class="text-xs font-semibold text-gray-500 mb-1">Data Metadata (JSON):</div>
            <pre class="p-3 rounded-lg bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 font-mono text-xs overflow-x-auto">{{ is_array($record->meta) ? json_encode($record->meta, JSON_PRETTY_PRINT) : $record->meta }}</pre>
        </div>
    @endif
</div>
