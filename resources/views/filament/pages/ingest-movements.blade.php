<x-filament-panels::page>
    <x-filament::section
        heading="Source logs"
        description="Paste a JSON array of movements (or upload a .json file). Logs may arrive in any order: they are re-sorted by occurred_at before any tank balance changes."
    >
        <textarea
            wire:model="json"
            rows="14"
            spellcheck="false"
            placeholder='[{"reference":"DIS-001","type":"discharge","tank_id":2,"product_id":1,"vessel_id":1,"declared_volume":"1000","occurred_at":"2026-11-03 09:00:00"}]'
            style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.8rem;padding:.75rem;border:1px solid rgba(128,128,128,.4);border-radius:.5rem;background:transparent;color:inherit;"
        ></textarea>

        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin-top:1rem;">
            <x-filament::button wire:click="process" icon="heroicon-m-play" wire:loading.attr="disabled">
                Process batch
            </x-filament::button>

            <x-filament::button wire:click="loadSample" color="gray" icon="heroicon-m-beaker">
                Load demo batch
            </x-filament::button>

            <input type="file" wire:model="upload" accept=".json,application/json,text/plain" />
        </div>
    </x-filament::section>

    @if ($processed)
        <x-filament::section heading="Result">
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
                <x-filament::badge color="success">{{ count($applied) }} applied</x-filament::badge>
                <x-filament::badge color="danger">{{ count($rejected) }} rejected</x-filament::badge>
                <x-filament::badge color="gray">{{ count($skipped) }} skipped (duplicates)</x-filament::badge>
            </div>

            @if ($batchId)
                <p style="margin-top:.75rem;font-size:.875rem;">
                    Saved as batch #{{ $batchId }}.
                    @if ($url = $this->batchUrl())
                        <a href="{{ $url }}" style="text-decoration:underline;">View it in batch history</a>
                    @endif
                </p>
            @endif
        </x-filament::section>

        @if (count($applied))
            <x-filament::section
                heading="Applied"
                description="Listed in the order they were applied (chronological by occurred_at), not the order they arrived."
            >
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.4);">
                                <th style="padding:.5rem;">#</th>
                                <th style="padding:.5rem;">Reference</th>
                                <th style="padding:.5rem;">Type</th>
                                <th style="padding:.5rem;">Tank</th>
                                <th style="padding:.5rem;">Occurred at</th>
                                <th style="padding:.5rem;text-align:right;">Declared (t)</th>
                                <th style="padding:.5rem;text-align:right;">Loss (t)</th>
                                <th style="padding:.5rem;text-align:right;">Net (t)</th>
                                <th style="padding:.5rem;text-align:right;">Tank balance (t)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applied as $row)
                                <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                    <td style="padding:.5rem;">{{ $row['seq'] }}</td>
                                    <td style="padding:.5rem;">{{ $row['reference'] }}</td>
                                    <td style="padding:.5rem;">{{ $row['type'] }}</td>
                                    <td style="padding:.5rem;">{{ $row['tank'] }}</td>
                                    <td style="padding:.5rem;">{{ $row['occurred_at'] }}</td>
                                    <td style="padding:.5rem;text-align:right;">{{ $row['declared'] }}</td>
                                    <td style="padding:.5rem;text-align:right;">{{ $row['loss'] }}</td>
                                    <td style="padding:.5rem;text-align:right;">{{ $row['net'] }}</td>
                                    <td style="padding:.5rem;text-align:right;">{{ $row['balance'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if (count($rejected))
            <x-filament::section heading="Rejected" description="Recorded in the ledger with the reason, so nothing is lost silently.">
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.4);">
                                <th style="padding:.5rem;">Reference</th>
                                <th style="padding:.5rem;">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rejected as $row)
                                <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                                    <td style="padding:.5rem;white-space:nowrap;">{{ $row['reference'] }}</td>
                                    <td style="padding:.5rem;">{{ $row['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if (count($skipped))
            <x-filament::section heading="Skipped" description="These references were already recorded, so they were not applied again.">
                <ul style="margin:0;padding-left:1.25rem;font-size:.875rem;">
                    @foreach ($skipped as $row)
                        <li>{{ $row['reference'] }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>