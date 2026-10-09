<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [FeatureFlagName::GithubOauth, 'Enables the GitHub login button on the admin login page.'],
            [FeatureFlagName::SocialReactions, 'Enables likes/dislikes on blog articles.'],
            [FeatureFlagName::ConsoleGreeting, 'Enables the ConsoleGreeting component on public pages.'],
        ];

        foreach ($flags as [$name, $description]) {
            FeatureFlagModel::query()->updateOrCreate(
                ['key' => $name->value],
                ['enabled' => false, 'description' => $description],
            );
        }
    }
}
