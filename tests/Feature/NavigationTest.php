<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    public function test_sidebar_groups_start_collapsed(): void
    {
        $groups = Filament::getCurrentOrDefaultPanel()->getNavigationGroups();

        $this->assertCount(4, $groups);

        foreach ($groups as $group) {
            $this->assertTrue($group->isCollapsed(), "{$group->getLabel()} should start collapsed");
        }
    }
}
