<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Enums;

/**
 * The five plain CTA click targets (analytics.md:19-22): the frontend
 * fires `cta_click_github`/`cta_click_demo`/`cta_click_cv`/
 * `cta_click_feedback`/`cta_click_projects` without payload metadata —
 * the target IS the event type (the report counts CTA clicks from the
 * type, not from metadata; B287). The language/theme switches are
 * separate event types with their own properties.
 */
enum CallToActionTarget: string
{
    case Github = 'github';

    case Demo = 'demo';

    case Cv = 'cv';

    case Feedback = 'feedback';

    case Projects = 'projects';
}
