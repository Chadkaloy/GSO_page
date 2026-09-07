<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CertificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $certifications = [
            [
                'name' => 'Juan Dela Cruz',
                'address' => '123 Main Street, Barangay Central, Quezon City',
                'violation' => 'Illegal parking in a no-parking zone',
                'date_of_violation' => '2024-01-15',
                'ordinance_no' => 'ORD-2024-001',
                'amount' => 1500.00,
                'receipt_no' => 'RC-2024-0001',
                'issued_date' => '2024-01-20',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Maria Santos',
                'address' => '456 Elm Street, Barangay South, Makati City',
                'violation' => 'Littering in public places',
                'date_of_violation' => '2024-01-18',
                'ordinance_no' => 'ORD-2024-002',
                'amount' => 800.00,
                'receipt_no' => 'RC-2024-0002',
                'issued_date' => '2024-01-22',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Pedro Reyes',
                'address' => '789 Oak Street, Barangay North, Mandaluyong City',
                'violation' => 'Noise violation during quiet hours',
                'date_of_violation' => '2024-01-22',
                'ordinance_no' => 'ORD-2024-003',
                'amount' => 1200.00,
                'receipt_no' => 'RC-2024-0003',
                'issued_date' => '2024-01-25',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Ana Lim',
                'address' => '321 Pine Street, Barangay East, Pasig City',
                'violation' => 'Obstructing pedestrian walkway',
                'date_of_violation' => '2024-01-25',
                'ordinance_no' => 'ORD-2024-004',
                'amount' => 1000.00,
                'receipt_no' => 'RC-2024-0004',
                'issued_date' => '2024-01-28',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Carlos Garcia',
                'address' => '654 Maple Street, Barangay West, Taguig City',
                'violation' => 'Unauthorized vending in restricted area',
                'date_of_violation' => '2024-01-28',
                'ordinance_no' => 'ORD-2024-005',
                'amount' => 2000.00,
                'receipt_no' => 'RC-2024-0005',
                'issued_date' => '2024-01-30',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Sofia Tan',
                'address' => '987 Cedar Street, Barangay Central, Manila',
                'violation' => 'Smoking in non-smoking area',
                'date_of_violation' => '2024-02-01',
                'ordinance_no' => 'ORD-2024-006',
                'amount' => 500.00,
                'receipt_no' => 'RC-2024-0006',
                'issued_date' => '2024-02-03',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Miguel Torres',
                'address' => '147 Walnut Street, Barangay South, Paranaque City',
                'violation' => 'Failure to segregate waste properly',
                'date_of_violation' => '2024-02-05',
                'ordinance_no' => 'ORD-2024-007',
                'amount' => 750.00,
                'receipt_no' => 'RC-2024-0007',
                'issued_date' => '2024-02-08',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Elena Ramirez',
                'address' => '258 Birch Street, Barangay North, Las Pinas City',
                'violation' => 'Illegal dumping of construction materials',
                'date_of_violation' => '2024-02-10',
                'ordinance_no' => 'ORD-2024-008',
                'amount' => 3000.00,
                'receipt_no' => 'RC-2024-0008',
                'issued_date' => '2024-02-12',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Antonio Chua',
                'address' => '369 Spruce Street, Barangay East, Marikina City',
                'violation' => 'Operating business without permit',
                'date_of_violation' => '2024-02-15',
                'ordinance_no' => 'ORD-2024-009',
                'amount' => 5000.00,
                'receipt_no' => 'RC-2024-0009',
                'issued_date' => '2024-02-18',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Isabel Ong',
                'address' => '741 Poplar Street, Barangay West, Valenzuela City',
                'violation' => 'Violation of building code regulations',
                'date_of_violation' => '2024-02-20',
                'ordinance_no' => 'ORD-2024-010',
                'amount' => 2500.00,
                'receipt_no' => 'RC-2024-0010',
                'issued_date' => '2024-02-22',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('certifications')->insert($certifications);
    }
}
