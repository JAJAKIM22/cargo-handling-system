<?php

namespace Database\Seeders;

use App\Domain\Cargo\Enums\ProductType;
use App\Domain\Cargo\Enums\TankStatus;
use App\Models\Product;
use App\Models\Tank;
use App\Models\Truck;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo login (change or remove before exposing the demo environment publicly)
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        // ---- Products ----
        $products = [];
        foreach (ProductType::cases() as $type) {
            $products[$type->value] = Product::updateOrCreate(
                ['code' => $type->value],
                [
                    'name' => $type->label(),
                    'description' => match ($type) {
                        ProductType::CPO => 'Unrefined bulk oil; needs constant temperature control.',
                        ProductType::CPOL => 'Liquid fraction after crystallization; higher fluidity.',
                        ProductType::PFAD => 'Refining by-product used in soap-making and animal feed.',
                    },
                ],
            );
        }

        // ---- Vessels ----
        $vessels = [];
        foreach ([
            'palm_voyager' => ['MV Palm Voyager', '9400001', 'PV-2026-11'],
            'olein_star' => ['MV Olein Star', '9400002', 'OS-2026-11'],
            'fatty_spirit' => ['MV Fatty Spirit', '9400003', 'FS-2026-10'],
            'golden_horizon' => ['MV Golden Horizon', '9400004', 'GH-2026-11'], // spare vessel to test the one-vessel-per-tank rule
        ] as $key => [$name, $imo, $voyage]) {
            $vessels[$key] = Vessel::updateOrCreate(
                ['imo_number' => $imo],
                ['name' => $name, 'voyage_reference' => $voyage],
            );
        }

        // ---- Trucks ----
        foreach ([
            ['KDA 101A', 'John Mwangi', 'Kenya Bulk Haulers'],
            ['KDB 202B', 'Grace Wanjiku', 'Kenya Bulk Haulers'],
            ['KDC 303C', 'Peter Otieno', 'Coast Tankers Ltd'],
            ['KDD 404D', 'Amina Hassan', 'Coast Tankers Ltd'],
        ] as [$plate, $driver, $transporter]) {
            Truck::updateOrCreate(
                ['plate_number' => $plate],
                ['driver_name' => $driver, 'transporter' => $transporter],
            );
        }

        // ---- Tanks (capacity and volume in tonnes) ----
        // Rule: a loaded tank must have BOTH product and vessel; an empty tank has neither.
        $tanks = [
            // code,   name,                 product,  vessel,           capacity, volume
            ['TK-01', 'Shore Tank 1 (CPO)',   'CPO',  'palm_voyager',   5000, 3200],
            ['TK-02', 'Shore Tank 2 (CPO)',   null,   null,             5000, 0],
            ['TK-03', 'Shore Tank 3 (CPOL)',  'CPOL', 'olein_star',     3000, 3000],
            ['TK-04', 'Shore Tank 4 (CPOL)',  null,   null,             3000, 0],
            ['TK-05', 'Shore Tank 5 (PFAD)',  'PFAD', 'fatty_spirit',   2000, 750],
            ['TK-06', 'Shore Tank 6 (PFAD)',  null,   null,             2000, 0],
        ];

        foreach ($tanks as [$code, $name, $productCode, $vesselKey, $capacity, $volume]) {
            Tank::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'product_id' => $productCode ? $products[$productCode]->id : null,
                    'current_vessel_id' => $vesselKey ? $vessels[$vesselKey]->id : null,
                    'capacity' => $capacity,
                    'current_volume' => $volume,
                    'status' => match (true) {
                        $volume <= 0 => TankStatus::Empty,
                        $volume >= $capacity => TankStatus::Full,
                        default => TankStatus::Partial,
                    },
                ],
            );
        }
    }
}