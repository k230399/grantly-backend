<?php

namespace Database\Seeders;

use App\Models\GrantRound;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class GrantRoundSeeder extends Seeder
{
    private string $adminId;

    // Seeds ten realistic Australian funding rounds: 6 open, 2 draft, 2 closed.
    // Cover images are left null; admins upload via the UI later.
    public function run(): void
    {
        // Fail loudly if the seeding admin is missing so the FK on created_by never silently breaks.
        $admin = User::where('email', 'user@example.com')
            ->where('role', 'admin')
            ->first();

        if (! $admin) {
            throw new RuntimeException(
                'GrantRoundSeeder requires an admin profile with email user@example.com. '
                . 'Create that admin first, then re-run the seeder.'
            );
        }

        $this->adminId = $admin->id;

        $rounds = [
            // OPEN ROUNDS (6)
            $this->openRound([
                'title'             => 'Regional Arts Development Fund 2026',
                'short_description' => 'Supporting regional and remote artists to develop new creative projects.',
                'description'       => "The Regional Arts Development Fund 2026 supports professional and emerging artists living and working in regional and remote Australia. Funding can be used for project development, materials, mentoring, equipment, or community engagement activities.\n\nWe particularly welcome projects that engage local communities, explore Australian stories, or expand opportunities for artists outside metropolitan centres.",
                'eligible_organisation_types' => 'Individual artists, arts collectives, and not-for-profit arts organisations.',
                'geographic_restrictions'     => 'Projects must be based outside Greater Sydney, Melbourne, Brisbane, Perth, Adelaide, and Canberra.',
                'eligibility_criteria'        => "Applicants must:\n• Be Australian citizens or permanent residents\n• Reside in regional or remote Australia (MMM 3+)\n• Have at least three years of professional or community arts practice\n• Hold appropriate public liability insurance for the project",
                'required_documents'  => ['Project Budget', 'Artist CV', 'Portfolio or Work Samples', 'Two Letters of Support'],
                'assessment_criteria' => "Applications are scored against:\n1. Artistic merit (30%)\n2. Community benefit (25%)\n3. Feasibility and budget (25%)\n4. Track record of the applicant (20%)",
                'key_focus_areas'     => ['Arts & Culture', 'Regional Development', 'Community Engagement'],
                'min_funding_amount'  => 5000,
                'max_funding_amount'  => 30000,
                'total_funding_pool'  => 600000,
                'is_featured'         => true,
                'contact_email'       => 'arts@example.gov.au',
                'contact_phone'       => '+61 2 8000 1100',
            ]),

            $this->openRound([
                'title'             => 'Indigenous Youth Leadership Grant 2026',
                'short_description' => 'Funding leadership programs for Aboriginal and Torres Strait Islander young people.',
                'description'       => "This grant supports community-led leadership and mentoring programs for Aboriginal and Torres Strait Islander young people aged 12–25. Programs may focus on cultural education, career pathways, civic engagement, or wellbeing.\n\nFunding decisions prioritise programs designed and delivered by First Nations organisations and communities.",
                'eligible_organisation_types' => 'Aboriginal and Torres Strait Islander Corporations (ORIC-registered), Indigenous-led not-for-profits, and partnerships with Indigenous community-controlled organisations.',
                'geographic_restrictions'     => 'Open to projects across all states and territories.',
                'eligibility_criteria'        => "Applicants must:\n• Be an Indigenous-led or Indigenous-controlled organisation\n• Demonstrate genuine community consultation and support\n• Have appropriate child-safe policies in place",
                'required_documents'  => ['Project Plan', 'Community Endorsement Letter', 'Annual Report', 'Child Safety Policy'],
                'assessment_criteria' => "Priority is given to projects that:\n• Are led by First Nations communities\n• Show clear youth co-design\n• Include cultural strengthening alongside skills development",
                'key_focus_areas'     => ['Indigenous Affairs', 'Youth', 'Education', 'Leadership'],
                'min_funding_amount'  => 10000,
                'max_funding_amount'  => 75000,
                'total_funding_pool'  => 1200000,
                'contact_email'       => 'first-nations@example.gov.au',
                'contact_phone'       => '+61 2 8000 1101',
            ]),

            $this->openRound([
                'title'             => 'Community Infrastructure Renewal Program',
                'short_description' => 'Capital works funding for community halls, sporting facilities, and shared spaces.',
                'description'       => "This program funds repairs, upgrades, and accessibility improvements for community-owned facilities. Eligible works include roof replacements, kitchen upgrades, accessible toilets and ramps, energy-efficiency retrofits, and shared digital infrastructure.\n\nApplicants are encouraged to source matched funding from their local council or community.",
                'eligible_organisation_types' => 'Incorporated associations, community-controlled facilities, and local sporting clubs.',
                'geographic_restrictions'     => 'Projects must be located in New South Wales or the Australian Capital Territory.',
                'eligibility_criteria'        => "Applicants must:\n• Hold either freehold title or a lease of at least 10 years over the facility\n• Be incorporated for at least two years\n• Provide a quantity surveyor's estimate for works over \$50,000",
                'required_documents'  => ['Project Scope', 'Quotes from Two Builders', 'Proof of Tenure', 'Insurance Certificate'],
                'assessment_criteria' => "Assessed on community benefit, value for money, urgency of works, and capacity to deliver on time.",
                'key_focus_areas'     => ['Infrastructure', 'Community Spaces', 'Accessibility'],
                'min_funding_amount'  => 20000,
                'max_funding_amount'  => 250000,
                'total_funding_pool'  => 4000000,
                'contact_email'       => 'infrastructure@example.gov.au',
                'contact_phone'       => '+61 2 8000 1102',
            ]),

            $this->openRound([
                'title'             => 'Mental Health First Aid Training Initiative',
                'short_description' => 'Subsidised mental health first aid training for community-facing workers.',
                'description'       => "This initiative funds accredited Mental Health First Aid (MHFA) training for staff and volunteers in community organisations. Participants learn how to recognise mental health crises, offer initial support, and connect people with professional services.\n\nWe will fully cover course fees and contribute toward backfill costs for participants released from frontline duties.",
                'eligible_organisation_types' => 'Not-for-profits, community legal centres, neighbourhood houses, faith-based organisations, and volunteer-led services.',
                'geographic_restrictions'     => 'Available nationally; rural and remote applicants given priority.',
                'eligibility_criteria'        => "Applicants must nominate at least three staff or volunteers who:\n• Hold an ongoing community-facing role\n• Have not previously completed an MHFA course\n• Will deliver at least 20 hours of community-facing work per month after training",
                'required_documents'  => ['List of Nominated Participants', 'Organisation Profile'],
                'assessment_criteria' => "All eligible applications will be funded until the round budget is exhausted.",
                'key_focus_areas'     => ['Mental Health', 'Workforce Development', 'Community Services'],
                'min_funding_amount'  => 1500,
                'max_funding_amount'  => 8000,
                'total_funding_pool'  => 250000,
                'is_featured'         => true,
                'contact_email'       => 'wellbeing@example.gov.au',
                'contact_phone'       => '+61 2 8000 1103',
            ]),

            $this->openRound([
                'title'             => 'Climate Resilience Small Grants',
                'short_description' => 'Quick-turnaround grants for community-led climate adaptation and resilience projects.',
                'description'       => "Climate Resilience Small Grants provide fast, flexible funding for grassroots projects that help communities prepare for and recover from extreme weather. Typical projects include community cool spaces, bushfire shelter signage, flood-ready toolkits, and local resilience networks.\n\nApplications are reviewed monthly, with funding decisions made within four weeks.",
                'eligible_organisation_types' => 'Community groups, Landcare networks, Resilient Communities Committees, and local councils.',
                'geographic_restrictions'     => 'Open Australia-wide.',
                'eligibility_criteria'        => "Applicants must demonstrate:\n• Direct involvement of community members in project design\n• A clear link between the project and a local climate risk\n• Capacity to deliver the project within nine months",
                'required_documents'  => ['Project Plan', 'Risk Statement', 'Budget'],
                'assessment_criteria' => "Decisions are made by a community panel based on community ownership, climate relevance, and value for money.",
                'key_focus_areas'     => ['Environment', 'Disaster Resilience', 'Sustainability'],
                'min_funding_amount'  => 1000,
                'max_funding_amount'  => 15000,
                'total_funding_pool'  => 350000,
                'allow_multiple_applications' => true,
                'max_applications_per_user'   => 3,
                'contact_email'       => 'resilience@example.gov.au',
                'contact_phone'       => '+61 2 8000 1104',
            ]),

            $this->openRound([
                'title'             => 'Volunteer Emergency Services Equipment Grant',
                'short_description' => 'Equipment funding for volunteer rural fire, SES, and marine rescue brigades.',
                'description'       => "This grant supports volunteer brigades and units to purchase essential operational equipment that is not provided through state agency budgets. Eligible items include personal protective gear, communications equipment, training props, vehicle modifications, and shed safety upgrades.\n\nThe grant cannot be used to purchase major appliances such as fire trucks, vessels, or vehicles.",
                'eligible_organisation_types' => 'Affiliated volunteer brigades and units of state emergency services agencies.',
                'geographic_restrictions'     => 'Open to brigades in all states and territories.',
                'eligibility_criteria'        => "Applicants must:\n• Be an active volunteer brigade or unit affiliated with a recognised state agency\n• Provide written endorsement from their state agency liaison",
                'required_documents'  => ['Equipment Quote', 'State Agency Endorsement Letter', 'Brigade Details'],
                'assessment_criteria' => "Assessed on operational need, frequency of brigade activations, and equity across regions.",
                'key_focus_areas'     => ['Emergency Services', 'Volunteers', 'Public Safety'],
                'min_funding_amount'  => 500,
                'max_funding_amount'  => 25000,
                'total_funding_pool'  => 800000,
                'contact_email'       => 'emergency-services@example.gov.au',
                'contact_phone'       => '+61 2 8000 1105',
            ]),

            // DRAFT ROUNDS (2)
            $this->draftRound([
                'title'             => 'Rural Sports Club Development Initiative',
                'short_description' => 'Funding for grassroots sporting clubs in rural and regional communities.',
                'description'       => "The Rural Sports Club Development Initiative will support clubs to grow participation, retain volunteers, and improve facilities for women, girls, and people with disability. Eligible activities include coach development, junior pathways, equipment purchases, and inclusive facility upgrades.\n\nThis round is currently being finalised and will open later in the year.",
                'eligible_organisation_types' => 'Incorporated community sporting clubs and regional sporting associations.',
                'geographic_restrictions'     => 'Clubs based in towns with a population under 25,000.',
                'eligibility_criteria'        => "Applicants must:\n• Be incorporated for at least one year\n• Have at least 30 active registered members\n• Hold current member protection and child-safe policies",
                'required_documents'  => ['Club Constitution', 'Most Recent Financial Statements', 'Member Protection Policy'],
                'assessment_criteria' => "Priority will be given to clubs increasing participation among under-represented groups.",
                'key_focus_areas'     => ['Sport & Recreation', 'Regional Development', 'Inclusion'],
                'min_funding_amount'  => 2000,
                'max_funding_amount'  => 40000,
                'total_funding_pool'  => 750000,
                'contact_email'       => 'sport@example.gov.au',
                'contact_phone'       => '+61 2 8000 1106',
            ]),

            $this->draftRound([
                'title'             => 'Disability Inclusion Innovation Fund',
                'short_description' => 'Pilot funding for new approaches to community participation by people with disability.',
                'description'       => "The Disability Inclusion Innovation Fund supports pilot projects that test new ways for people with disability to participate in community, civic, and economic life. Examples include co-designed transport solutions, peer-led mentoring, accessible recreation programs, and digital inclusion initiatives.\n\nThe round is in development; applications will open in the next quarter.",
                'eligible_organisation_types' => 'Disabled Persons Organisations (DPOs), disability service providers, and consortia including DPOs.',
                'geographic_restrictions'     => 'Open Australia-wide.',
                'eligibility_criteria'        => "Applicants must:\n• Demonstrate genuine co-design with people with disability\n• Show how learnings will be shared publicly at the conclusion of the project",
                'required_documents'  => ['Project Plan', 'Co-design Statement', 'Evaluation Plan', 'Budget'],
                'assessment_criteria' => "Assessed on innovation, co-design quality, scalability, and learning transfer.",
                'key_focus_areas'     => ['Disability', 'Inclusion', 'Innovation'],
                'min_funding_amount'  => 25000,
                'max_funding_amount'  => 150000,
                'total_funding_pool'  => 1500000,
                'is_featured'         => true,
                'contact_email'       => 'inclusion@example.gov.au',
                'contact_phone'       => '+61 2 8000 1107',
            ]),

            // CLOSED ROUNDS (2)
            $this->closedRound([
                'title'             => 'Regional Library Modernisation Fund',
                'short_description' => 'Capital funding to modernise public library facilities and digital services.',
                'description'       => "The Regional Library Modernisation Fund provided one-off capital funding to upgrade public library buildings, expand digital access, and refresh collections. The round closed earlier this year and recipients have been announced.\n\nA summary of funded projects is available on the program webpage.",
                'eligible_organisation_types' => 'Local government authorities operating public library services.',
                'geographic_restrictions'     => 'Local government areas outside metropolitan capital cities.',
                'eligibility_criteria'        => "Applicants were required to:\n• Operate at least one public library branch\n• Provide co-contribution of at least 25% of project costs",
                'required_documents'  => ['Project Scope', 'Council Endorsement', 'Budget with Co-contribution'],
                'assessment_criteria' => "Assessed on community impact, equity of access, and long-term sustainability.",
                'key_focus_areas'     => ['Libraries', 'Digital Inclusion', 'Local Government'],
                'min_funding_amount'  => 50000,
                'max_funding_amount'  => 500000,
                'total_funding_pool'  => 6000000,
                'contact_email'       => 'libraries@example.gov.au',
                'contact_phone'       => '+61 2 8000 1108',
            ]),

            $this->closedRound([
                'title'             => 'Australia Day Community Events Grant 2025',
                'short_description' => 'Small grants supporting community-run Australia Day events in 2025.',
                'description'       => "This program provided small grants for community-run events celebrating Australia Day on 26 January 2025. Funded events included citizenship ceremonies, community breakfasts, multicultural concerts, and Welcome to Country events.\n\nThis round is now closed; the 2026 program will be announced separately.",
                'eligible_organisation_types' => 'Local councils, community groups, multicultural associations, and Reconciliation Action Plan groups.',
                'geographic_restrictions'     => 'Open Australia-wide.',
                'eligibility_criteria'        => "Applicants were required to:\n• Hold a free community event open to the public\n• Comply with state public event safety requirements",
                'required_documents'  => ['Event Plan', 'Risk Management Plan', 'Public Liability Certificate'],
                'assessment_criteria' => "Assessed on community reach, inclusivity, and value for money.",
                'key_focus_areas'     => ['Community Events', 'Multiculturalism', 'Reconciliation'],
                'min_funding_amount'  => 500,
                'max_funding_amount'  => 10000,
                'total_funding_pool'  => 500000,
                'allow_multiple_applications' => true,
                'max_applications_per_user'   => 2,
                'contact_email'       => 'events@example.gov.au',
                'contact_phone'       => '+61 2 8000 1109',
            ]),
        ];

        foreach ($rounds as $round) {
            GrantRound::create($round);
        }

        $this->command->info(sprintf(
            'Seeded %d grant rounds against admin %s.',
            count($rounds),
            $admin->email,
        ));
    }

    // Defaults for a published, currently-open round.
    private function openRound(array $overrides): array
    {
        return array_merge([
            'cover_image_url'             => null,
            'application_form_schema'     => null,
            'status'                      => 'open',
            'is_published'                => true,
            'is_featured'                 => false,
            'allow_multiple_applications' => false,
            'max_applications_per_user'   => 1,
            'opens_at'                    => now()->subWeeks(4),
            'closes_at'                   => now()->addWeeks(8),
            'assessment_period_start'     => now()->addWeeks(9),
            'notification_date'           => now()->addWeeks(13),
            'funding_release_date'        => now()->addWeeks(16),
            'published_at'                => now()->subWeeks(5),
            'closed_at'                   => null,
            'created_by'                  => $this->adminId,
        ], $overrides);
    }

    // Defaults for an unpublished draft round (future dates, hidden from applicants).
    private function draftRound(array $overrides): array
    {
        return array_merge([
            'cover_image_url'             => null,
            'application_form_schema'     => null,
            'status'                      => 'draft',
            'is_published'                => false,
            'is_featured'                 => false,
            'allow_multiple_applications' => false,
            'max_applications_per_user'   => 1,
            'opens_at'                    => now()->addWeeks(6),
            'closes_at'                   => now()->addWeeks(18),
            'assessment_period_start'     => now()->addWeeks(19),
            'notification_date'           => now()->addWeeks(23),
            'funding_release_date'        => now()->addWeeks(26),
            'published_at'                => null,
            'closed_at'                   => null,
            'created_by'                  => $this->adminId,
        ], $overrides);
    }

    // Defaults for a closed round (opened and closed in the past, closed_at stamped).
    private function closedRound(array $overrides): array
    {
        return array_merge([
            'cover_image_url'             => null,
            'application_form_schema'     => null,
            'status'                      => 'closed',
            'is_published'                => true,
            'is_featured'                 => false,
            'allow_multiple_applications' => false,
            'max_applications_per_user'   => 1,
            'opens_at'                    => now()->subMonths(8),
            'closes_at'                   => now()->subMonths(2),
            'assessment_period_start'     => now()->subWeeks(7),
            'notification_date'           => now()->subWeeks(2),
            'funding_release_date'        => now()->addWeeks(2),
            'published_at'                => now()->subMonths(9),
            'closed_at'                   => now()->subMonths(2),
            'created_by'                  => $this->adminId,
        ], $overrides);
    }
}
