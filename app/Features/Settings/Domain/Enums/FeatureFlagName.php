<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\Enums;

/**
 * The strictly defined feature flag key names (database.md "Initial flags").
 */
enum FeatureFlagName: string
{
    case GithubOauth = 'github_oauth';
    case SocialReactions = 'social_reactions';
    case ConsoleGreeting = 'console_greeting';
}
