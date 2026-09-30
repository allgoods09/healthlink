<?php

namespace App\Support\Population;

use Illuminate\Support\Str;
use Random\Randomizer;
use RuntimeException;

class PoocNames
{
    private array $used = [];

    private array $given;

    private array $weights;

    public function __construct(private Randomizer $random)
    {
        $this->given = array_map(fn ($pool) => array_values(array_unique($pool)), require database_path('data/pooc-given-names.php'));
        $this->weights = array_column(require database_path('data/tubigon-surnames.php'), 'incidence', 'surname');
    }

    public function weighted(array $weights): string|int
    {
        $ticket = $this->random->getInt(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            $ticket -= $weight;
            if ($ticket <= 0) {
                return $value;
            }
        }
        throw new RuntimeException('Invalid sampling weights.');
    }

    public function surname(): string
    {
        return $this->weighted($this->weights);
    }

    public function name(string $sex, string $middle, string $last, ?string $suffix = null, ?string $preferred = null): array
    {
        $pool = $this->given[$sex];
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $first = $preferred ?? $pool[$this->random->getInt(0, count($pool) - 1)];
            if (! $preferred && ! str_contains($first, ' ') && $this->random->getInt(1, 100) <= 32) {
                $single = array_values(array_filter($pool, fn ($name) => ! str_contains($name, ' ') && $name !== $first));
                $first .= ' '.$single[$this->random->getInt(0, count($single) - 1)];
                if ($this->random->getInt(1, 100) <= 4) {
                    $third = array_values(array_filter($single, fn ($name) => ! in_array($name, explode(' ', $first), true)));
                    $first .= ' '.$third[$this->random->getInt(0, count($third) - 1)];
                }
            }
            $parts = ['first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'suffix' => $suffix];
            $key = self::normalized($parts);
            if (! isset($this->used[$key])) {
                $this->used[$key] = true;

                return $parts;
            }
            // Retry vocabulary selection, never append a number to manufacture uniqueness.
            $preferred = null;
        }
        throw new RuntimeException('Given-name vocabulary exhausted for this family.');
    }

    public static function normalized(array $parts): string
    {
        $full = implode(' ', array_map(fn ($key) => $parts[$key] ?? '', ['first_name', 'middle_name', 'last_name', 'suffix']));

        return preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($full)));
    }
}
