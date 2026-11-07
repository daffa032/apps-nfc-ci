<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

/**
 * Brand logo and header background per profile and template (PRD BR-01). Every method is scoped by
 * the profile ID the service resolved for the signed-in owner.
 */
class BrandAssetRepository
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /**
     * @return array{brand_logo: ?string, header_background: ?string, brand_logo_visible: bool, header_background_visible: bool}|null
     */
    public function find(int $profileId, string $templateFile): ?array
    {
        $row = $this->db->table('card_brand_assets')
            ->select('brand_logo, header_background, brand_logo_visible, header_background_visible')
            ->where('profile_id', $profileId)
            ->where('template_file', $templateFile)
            ->get()->getRowArray();
        if (!$row) {
            return null;
        }
        $row['brand_logo_visible'] = (int) $row['brand_logo_visible'] === 1;
        $row['header_background_visible'] = (int) $row['header_background_visible'] === 1;

        return $row;
    }

    /**
     * Writes the given fields for one template: brand_logo / header_background (null clears) and
     * their *_visible switches (bool).
     *
     * @param array<string, string|bool|null> $fields
     */
    public function save(int $profileId, string $templateFile, array $fields, string $nowUtc): void
    {
        $fields = array_intersect_key($fields, ['brand_logo' => true, 'header_background' => true, 'brand_logo_visible' => true, 'header_background_visible' => true]);
        foreach (['brand_logo_visible', 'header_background_visible'] as $switch) {
            if (array_key_exists($switch, $fields)) {
                $fields[$switch] = $fields[$switch] ? 1 : 0;
            }
        }
        if ($fields === []) {
            return;
        }
        $columns = array_merge(['profile_id', 'template_file', 'updated_at'], array_keys($fields));
        $values = array_merge([$profileId, $templateFile, $nowUtc], array_values($fields));
        $updates = implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", array_merge(['updated_at'], array_keys($fields))));
        $this->db->query(
            'INSERT INTO ' . $this->db->prefixTable('card_brand_assets') . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ') ON DUPLICATE KEY UPDATE ' . $updates,
            $values
        );
    }
}
