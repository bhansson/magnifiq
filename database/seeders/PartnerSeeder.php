<?php

namespace Database\Seeders;

use App\Models\PartnerRevenue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create superadmin user with personal team
        $admin = User::factory()->withPersonalTeam()->create([
            'name' => 'Super Admin',
            'email' => 'admin@magnifiq.com',
            'role' => 'superadmin',
        ]);

        // Create partner users with personal teams
        $partnerOwner1 = User::factory()->withPersonalTeam()->create([
            'name' => 'Acme Partner Owner',
            'email' => 'owner@acme.com',
        ]);

        $partnerOwner2 = User::factory()->withPersonalTeam()->create([
            'name' => 'Beta Solutions Owner',
            'email' => 'owner@beta.com',
        ]);

        $partnerOwner3 = User::factory()->withPersonalTeam()->create([
            'name' => 'Gamma Agency Owner',
            'email' => 'owner@gamma.com',
        ]);

        // Create Partner 1: Acme Corp (with branding)
        $acmePartner = Team::create([
            'user_id' => $partnerOwner1->id,
            'name' => 'Acme Corporation',
            'type' => 'partner',
            'partner_slug' => 'acme',
            'logo_path' => null, // In production, this would be uploaded
            'personal_team' => false,
        ]);

        // Add admin as member of Acme partner team
        $acmePartner->users()->attach($admin, ['role' => 'admin']);

        // Create Partner 2: Beta Solutions
        $betaPartner = Team::create([
            'user_id' => $partnerOwner2->id,
            'name' => 'Beta Solutions',
            'type' => 'partner',
            'partner_slug' => 'beta',
            'logo_path' => null,
            'personal_team' => false,
        ]);

        $betaPartner->users()->attach($admin, ['role' => 'admin']);

        // Create Partner 3: Gamma Agency (no slug, minimal setup)
        $gammaPartner = Team::create([
            'user_id' => $partnerOwner3->id,
            'name' => 'Gamma Digital Agency',
            'type' => 'partner',
            'partner_slug' => 'gamma',
            'logo_path' => null,
            'personal_team' => false,
        ]);

        // Create customer teams for Acme
        $acmeCustomers = [
            ['name' => 'TechStartup Inc', 'revenue' => 15000], // $150/month
            ['name' => 'DesignStudio Ltd', 'revenue' => 25000], // $250/month
            ['name' => 'MarketingPro Agency', 'revenue' => 35000], // $350/month
        ];

        foreach ($acmeCustomers as $customerData) {
            $customerOwner = User::factory()->create([
                'name' => "{$customerData['name']} Owner",
                'email' => strtolower(str_replace(' ', '', $customerData['name'])) . '@example.com',
            ]);

            $customer = Team::create([
                'user_id' => $customerOwner->id,
                'name' => $customerData['name'],
                'type' => 'customer',
                'parent_team_id' => $acmePartner->id,
                'personal_team' => false,
            ]);

            // Add partner owner to customer team
            $customer->users()->attach($partnerOwner1, ['role' => 'partner_admin']);

            // Create revenue records (3 months of history)
            for ($i = 0; $i < 3; $i++) {
                $periodStart = now()->subMonths($i)->startOfMonth();
                $periodEnd = now()->subMonths($i)->endOfMonth();

                PartnerRevenue::create([
                    'partner_team_id' => $acmePartner->id,
                    'customer_team_id' => $customer->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'customer_revenue_cents' => $customerData['revenue'],
                    'partner_share_percent' => 20.00, // 20% revenue share for Acme
                    'partner_revenue_cents' => (int) ($customerData['revenue'] * 0.20),
                    'currency' => 'USD',
                    'notes' => $i === 0 ? 'Current month' : "Month -$i",
                ]);
            }
        }

        // Create customer teams for Beta
        $betaCustomers = [
            ['name' => 'E-commerce Ventures', 'revenue' => 50000], // $500/month
            ['name' => 'SaaS Startup Co', 'revenue' => 30000], // $300/month
        ];

        foreach ($betaCustomers as $customerData) {
            $customerOwner = User::factory()->create([
                'name' => "{$customerData['name']} Owner",
                'email' => strtolower(str_replace(' ', '', $customerData['name'])) . '@example.com',
            ]);

            $customer = Team::create([
                'user_id' => $customerOwner->id,
                'name' => $customerData['name'],
                'type' => 'customer',
                'parent_team_id' => $betaPartner->id,
                'personal_team' => false,
            ]);

            $customer->users()->attach($partnerOwner2, ['role' => 'partner_admin']);

            // Create revenue records (2 months of history)
            for ($i = 0; $i < 2; $i++) {
                $periodStart = now()->subMonths($i)->startOfMonth();
                $periodEnd = now()->subMonths($i)->endOfMonth();

                PartnerRevenue::create([
                    'partner_team_id' => $betaPartner->id,
                    'customer_team_id' => $customer->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'customer_revenue_cents' => $customerData['revenue'],
                    'partner_share_percent' => 15.00, // 15% revenue share for Beta
                    'partner_revenue_cents' => (int) ($customerData['revenue'] * 0.15),
                    'currency' => 'USD',
                    'notes' => $i === 0 ? 'Current month' : "Month -$i",
                ]);
            }
        }

        // Create one customer for Gamma (new partner with minimal activity)
        $gammaCustomerOwner = User::factory()->create([
            'name' => 'Local Business Owner',
            'email' => 'owner@localbiz.com',
        ]);

        $gammaCustomer = Team::create([
            'user_id' => $gammaCustomerOwner->id,
            'name' => 'Local Business Co',
            'type' => 'customer',
            'parent_team_id' => $gammaPartner->id,
            'personal_team' => false,
        ]);

        $gammaCustomer->users()->attach($partnerOwner3, ['role' => 'partner_admin']);

        // Create just current month revenue for Gamma
        PartnerRevenue::create([
            'partner_team_id' => $gammaPartner->id,
            'customer_team_id' => $gammaCustomer->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 10000, // $100/month
            'partner_share_percent' => 25.00, // 25% revenue share for Gamma
            'partner_revenue_cents' => 2500, // $25
            'currency' => 'USD',
            'notes' => 'First month - new partnership',
        ]);

        // Create a few unassigned customer teams (available for assignment)
        for ($i = 1; $i <= 3; $i++) {
            $unassignedOwner = User::factory()->create([
                'name' => "Unassigned Customer $i Owner",
                'email' => "unassigned$i@example.com",
            ]);

            Team::create([
                'user_id' => $unassignedOwner->id,
                'name' => "Unassigned Customer $i",
                'type' => 'customer',
                'parent_team_id' => null, // Not assigned to any partner
                'personal_team' => false,
            ]);
        }

        $this->command->info('✅ Partner seeder completed!');
        $this->command->info('📊 Created:');
        $this->command->info('   - 3 partner teams (Acme, Beta, Gamma)');
        $this->command->info('   - 6 customer teams (3 for Acme, 2 for Beta, 1 for Gamma)');
        $this->command->info('   - 3 unassigned customer teams');
        $this->command->info('   - 11 revenue records across multiple months');
        $this->command->info('');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('   Super Admin: admin@magnifiq.com / password (role: superadmin)');
        $this->command->info('   Acme Partner: owner@acme.com / password (role: user)');
        $this->command->info('   Beta Partner: owner@beta.com / password (role: user)');
    }
}
