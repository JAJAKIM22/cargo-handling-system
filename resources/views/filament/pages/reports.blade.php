<x-filament-panels::page>
    <x-filament::section
        heading="Terminal report (Excel)"
        description="Use the Download button at the top right and choose a date range. The workbook contains four sheets:"
    >
        <ul style="margin:0;padding-left:1.25rem;font-size:.9rem;line-height:1.7;">
            <li><strong>Summary</strong>: per product, discharges, declared volume, 0.5% transit loss, net landed, deliveries, delivered volume and net change, with totals and the number of rejected movements.</li>
            <li><strong>Tanks</strong>: a snapshot of every tank right now (product, vessel, volume, free space, fill %). It ignores the date range.</li>
            <li><strong>Movements</strong>: the full ledger for the period, including rejected movements with their reasons, balances before and after, who recorded them and which batch they came from.</li>
            <li><strong>Vessels</strong>: per vessel, declared volume against landed volume and the transit loss.</li>
        </ul>
        <p style="margin-top:1rem;font-size:.85rem;opacity:.75;">All volumes are in metric tonnes (t). Summary and Vessels count applied movements only.</p>
    </x-filament::section>
</x-filament-panels::page>