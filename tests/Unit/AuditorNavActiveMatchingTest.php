<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lightweight mirror of resources/js/config/navigation.ts matching rules
 * so PHPUnit can verify active-nav behaviour without a JS runner.
 */
class AuditorNavActiveMatchingTest extends TestCase
{
    /**
     * @param  array{href: string, match?: list<string>}  $item
     */
    private function score(array $item, string $currentPath): int
    {
        $path = explode('?', explode('#', $currentPath)[0])[0];
        $patterns = array_merge(
            [explode('?', $item['href'])[0]],
            $item['match'] ?? [],
        );

        $best = 0;
        foreach ($patterns as $pattern) {
            if ($path === $pattern) {
                $best = max($best, strlen($pattern) * 10 + 5000);

                continue;
            }
            if (str_ends_with($pattern, '/dashboard')) {
                continue;
            }
            if (str_starts_with($path, $pattern.'/')) {
                $best = max($best, strlen($pattern) * 10);
            }
        }

        return $best;
    }

    /**
     * @param  list<array{href: string, match?: list<string>, key: string}>  $items
     */
    private function activeKey(array $items, string $currentPath): ?string
    {
        $bestScore = 0;
        $bestKey = null;
        foreach ($items as $item) {
            $score = $this->score($item, $currentPath);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $item['key'];
            }
        }

        return $bestScore > 0 ? $bestKey : null;
    }

    public function test_auditor_nav_active_item_matches_current_route(): void
    {
        $items = [
            ['key' => 'dashboard', 'href' => '/auditor/dashboard'],
            ['key' => 'assigned', 'href' => '/auditor/assigned-audits'],
            ['key' => 'leads', 'href' => '/auditor/audits', 'match' => ['/auditor/audits']],
            ['key' => 'completed', 'href' => '/auditor/completed-audits'],
            ['key' => 'messages', 'href' => '/auditor/messages'],
            ['key' => 'profile', 'href' => '/auditor/profile'],
        ];

        $this->assertSame('dashboard', $this->activeKey($items, '/auditor/dashboard'));
        $this->assertSame('assigned', $this->activeKey($items, '/auditor/assigned-audits'));
        $this->assertSame('leads', $this->activeKey($items, '/auditor/audits'));
        $this->assertSame('leads', $this->activeKey($items, '/auditor/audits/12'));
        $this->assertSame('completed', $this->activeKey($items, '/auditor/completed-audits'));
        $this->assertSame('messages', $this->activeKey($items, '/auditor/messages/3'));
        $this->assertSame('profile', $this->activeKey($items, '/auditor/profile'));
    }

    public function test_more_specific_seller_route_wins_over_parent(): void
    {
        $items = [
            ['key' => 'create', 'href' => '/seller/leads/create'],
            ['key' => 'list', 'href' => '/seller/leads'],
        ];

        $this->assertSame('create', $this->activeKey($items, '/seller/leads/create'));
        $this->assertSame('list', $this->activeKey($items, '/seller/leads'));
        $this->assertSame('list', $this->activeKey($items, '/seller/leads/99'));
    }
}
