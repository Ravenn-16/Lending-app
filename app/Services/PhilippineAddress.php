<?php
namespace App\Services;
class PhilippineAddress
{
    private array $data;
    public function __construct()
    {
        $this->data = json_decode(file_get_contents(resource_path('data/philippine-addresses.json')), true, 512, JSON_THROW_ON_ERROR);
    }
    public function provinces(): array { return $this->data['provinces']; }
    public function cities(string $province): array { return $this->data['cities'][$province] ?? []; }
    public function barangays(string $city): array { return $this->data['barangays'][$city] ?? []; }
    public function find(array $items, string $code): ?array
    {
        foreach ($items as $item) if ($item['code'] === $code) return $item;
        return null;
    }
    public function resolve(string $province, string $city, string $barangay): ?string
    {
        $p = $this->find($this->provinces(), $province);
        $c = $this->find($this->cities($province), $city);
        $b = $c ? $this->find($this->barangays($city), $barangay) : null;
        return $p && $c && $b ? implode(', ', [$b['name'], $c['name'], $p['name']]) : null;
    }
}
