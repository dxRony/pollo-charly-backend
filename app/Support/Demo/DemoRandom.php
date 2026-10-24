<?php

declare(strict_types=1);

namespace App\Support\Demo;

use Random\Engine\Mt19937;
use Random\Randomizer;

/** Generador de números aleatorios con semilla: la misma semilla produce siempre los mismos datos de demostración. */
final class DemoRandom
{
    private readonly Randomizer $randomizer;

    public function __construct(int $seed)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    /** @param int $percent Probabilidad de 0 a 100. */
    public function chance(int $percent): bool
    {
        return $this->randomizer->getInt(1, 100) <= $percent;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->randomizer->getInt(0, count($items) - 1)];
    }

    /**
     * Elige una clave con probabilidad proporcional a su peso.
     *
     * @param  array<int|string, int>  $weights
     */
    public function weighted(array $weights): int|string
    {
        $roll = $this->randomizer->getInt(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    public function shuffle(array $items): array
    {
        return $this->randomizer->shuffleArray($items);
    }
}
