<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

use GeolizeStudio\Chile\Exceptions\InvalidTerritoryData;
use GeolizeStudio\Chile\Territory\Comuna;
use GeolizeStudio\Chile\Territory\ComunaResolution;
use GeolizeStudio\Chile\Territory\MatchMethod;
use GeolizeStudio\Chile\Territory\Province;
use GeolizeStudio\Chile\Territory\Region;
use InvalidArgumentException;
use JsonException;
use Normalizer;

final readonly class Territory
{
    /** @var array<string, Region> */
    private array $regionIndex;

    /** @var array<string, Province> */
    private array $provinceIndex;

    /** @var array<string, Comuna> */
    private array $comunaIndex;

    /** @var array<string, list<Comuna>> */
    private array $aliasIndex;

    /**
     * @param  array<string, Region>  $regions
     * @param  array<string, Province>  $provinces
     * @param  array<string, Comuna>  $comunas
     * @param  array<array-key, mixed>  $aliases
     */
    private function __construct(
        public string $datasetVersion,
        array $regions,
        array $provinces,
        array $comunas,
        array $aliases,
    ) {
        $this->regionIndex = $regions;
        $this->provinceIndex = $provinces;
        $this->comunaIndex = $comunas;
        $index = [];
        foreach ($aliases as $alias => $codes) {
            if (! is_string($alias) || $alias === '' || ! is_array($codes) || ! array_is_list($codes) || $codes === []) {
                throw new InvalidTerritoryData('Aliases must map nonempty names to nonempty lists of comuna CUT strings.');
            }
            [$normalized] = self::normalize($alias);
            if ($normalized === '' || $normalized === 'comuna de' || preg_match('/^[0-9]+$/D', $normalized) === 1) {
                throw new InvalidTerritoryData('Aliases must be names, not CUT codes.');
            }
            foreach ($codes as $code) {
                if (! is_string($code) || ! isset($comunas['c:'.$code])) {
                    throw new InvalidTerritoryData('An alias refers to an unknown comuna CUT.');
                }
                $index['a:'.$normalized]['c:'.$code] = $comunas['c:'.$code];
            }
        }
        $this->aliasIndex = array_map(static function (array $matches): array {
            ksort($matches, SORT_STRING);

            return array_values($matches);
        }, $index);
    }

    /** @param array<array-key, mixed> $aliases */
    public static function loadDefault(array $aliases = []): self
    {
        $directory = dirname(__DIR__).'/resources/territory';
        $manifest = self::readJson($directory.'/catalog-manifest.json');
        $hash = $manifest['catalog_sha256'] ?? null;
        if (! is_string($hash) || ! hash_equals($hash, (string) hash_file('sha256', $directory.'/catalog.json'))) {
            throw new InvalidTerritoryData('The bundled territory catalog does not match its manifest.');
        }

        return self::fromFile($directory.'/catalog.json', $aliases);
    }

    /** @param array<array-key, mixed> $aliases */
    public static function fromFile(string $path, array $aliases = []): self
    {
        $data = self::readJson($path);
        self::requireKeys($data, ['schema_version', 'dataset_version', 'regions', 'provinces', 'comunas']);
        if ($data['schema_version'] !== 1 || ! is_string($data['dataset_version']) || trim($data['dataset_version']) === '') {
            throw new InvalidTerritoryData('Unsupported territory catalog schema or missing dataset version.');
        }
        $regions = [];
        foreach (self::rows($data['regions']) as $row) {
            self::requireKeys($row, ['code', 'name']);
            $code = self::code($row['code'], 2);
            self::unique($regions, 'r:'.$code);
            $regions['r:'.$code] = new Region($code, self::name($row['name']));
        }
        $provinces = [];
        foreach (self::rows($data['provinces']) as $row) {
            self::requireKeys($row, ['code', 'name', 'region_code']);
            $code = self::code($row['code'], 3);
            $region = self::code($row['region_code'], 2);
            self::unique($provinces, 'p:'.$code);
            if (! isset($regions['r:'.$region]) || ! str_starts_with($code, $region)) {
                throw new InvalidTerritoryData('A province has an invalid region relationship.');
            }
            $provinces['p:'.$code] = new Province($code, self::name($row['name']), $region);
        }
        $comunas = [];
        foreach (self::rows($data['comunas']) as $row) {
            self::requireKeys($row, ['code', 'name', 'province_code', 'region_code']);
            $code = self::code($row['code'], 5);
            $province = self::code($row['province_code'], 3);
            $region = self::code($row['region_code'], 2);
            self::unique($comunas, 'c:'.$code);
            if (! isset($provinces['p:'.$province]) || ! str_starts_with($code, $province) || $provinces['p:'.$province]->regionCode !== $region) {
                throw new InvalidTerritoryData('A comuna has an invalid province or region relationship.');
            }
            $comunas['c:'.$code] = new Comuna($code, self::name($row['name']), $province, $region);
        }
        if (count(array_unique(array_map(static fn (Province $province): string => $province->regionCode, $provinces))) !== count($regions)
            || count(array_unique(array_map(static fn (Comuna $comuna): string => $comuna->provinceCode, $comunas))) !== count($provinces)) {
            throw new InvalidTerritoryData('Every region and province must have a child record.');
        }
        ksort($regions, SORT_STRING);
        ksort($provinces, SORT_STRING);
        ksort($comunas, SORT_STRING);

        return new self($data['dataset_version'], $regions, $provinces, $comunas, $aliases);
    }

    public function region(string $code): ?Region
    {
        return $this->regionIndex['r:'.$code] ?? null;
    }

    public function province(string $code): ?Province
    {
        return $this->provinceIndex['p:'.$code] ?? null;
    }

    public function comuna(string $code): ?Comuna
    {
        return $this->comunaIndex['c:'.$code] ?? null;
    }

    /** @return list<Region> */
    public function regions(): array
    {
        return array_values($this->regionIndex);
    }

    /** @return list<Province> */
    public function provinces(?string $regionCode = null): array
    {
        return array_values(array_filter($this->provinceIndex, static fn (Province $province): bool => $regionCode === null || $province->regionCode === $regionCode));
    }

    /** @return list<Comuna> */
    public function comunas(?string $regionCode = null, ?string $provinceCode = null): array
    {
        return array_values(array_filter($this->comunaIndex, static fn (Comuna $comuna): bool => ($regionCode === null || $comuna->regionCode === $regionCode) && ($provinceCode === null || $comuna->provinceCode === $provinceCode)));
    }

    public function resolveComuna(string $input, ?string $regionCode = null): ComunaResolution
    {
        if (! mb_check_encoding($input, 'UTF-8')) {
            throw new InvalidArgumentException('Comuna input must be valid UTF-8; encoding repair is not automatic.');
        }
        if ($regionCode !== null && $this->region($regionCode) === null) {
            throw new InvalidArgumentException('Resolution context must reference a known region CUT.');
        }
        $scope = $this->comunas($regionCode);
        if (preg_match('/^[0-9]{5}$/D', $input) === 1) {
            $matches = array_values(array_filter($scope, static fn (Comuna $comuna): bool => $comuna->code === $input));

            return new ComunaResolution($input, MatchMethod::Code, [], $matches);
        }
        $exact = array_values(array_filter($scope, static fn (Comuna $comuna): bool => $comuna->name === $input));
        if ($exact !== []) {
            return new ComunaResolution($input, MatchMethod::Exact, [], $exact);
        }
        [$normalized, $transformations] = self::normalize($input);
        if ($normalized === '') {
            return new ComunaResolution($input, null, $transformations, []);
        }
        $matches = array_values(array_filter($scope, static fn (Comuna $comuna): bool => self::normalize($comuna->name)[0] === $normalized));
        if ($matches !== []) {
            return new ComunaResolution($input, MatchMethod::Normalized, $transformations, $matches);
        }
        $aliases = array_values(array_filter($this->aliasIndex['a:'.$normalized] ?? [], static fn (Comuna $comuna): bool => $regionCode === null || $comuna->regionCode === $regionCode));

        return new ComunaResolution($input, $aliases === [] ? null : MatchMethod::Alias, $transformations, $aliases);
    }

    /** @return array{string, list<string>} */
    private static function normalize(string $input): array
    {
        if (! mb_check_encoding($input, 'UTF-8')) {
            throw new InvalidTerritoryData('Territory names and aliases must be valid UTF-8.');
        }
        $value = $input;
        $transformations = [];
        $steps = [
            'unicode_nfc' => static fn (string $s): string => self::normalForm($s, Normalizer::FORM_C),
            'trim_whitespace' => static fn (string $s): string => (string) preg_replace('/^\s+|\s+$/u', '', $s),
            'collapse_whitespace' => static fn (string $s): string => (string) preg_replace('/\s+/u', ' ', $s),
            'lowercase' => static fn (string $s): string => mb_strtolower($s, 'UTF-8'),
            'remove_diacritics' => static fn (string $s): string => self::normalForm((string) preg_replace('/\p{Mn}+/u', '', self::normalForm($s, Normalizer::FORM_D)), Normalizer::FORM_C),
            'remove_comuna_prefix' => static fn (string $s): string => str_starts_with($s, 'comuna de ') ? substr($s, 10) : $s,
        ];
        foreach ($steps as $name => $step) {
            $next = $step($value);
            if ($next !== $value) {
                $transformations[] = $name;
            }
            $value = $next;
        }

        return [$value, $transformations];
    }

    private static function normalForm(string $value, int $form): string
    {
        $normalized = Normalizer::normalize($value, $form);
        if (! is_string($normalized)) {
            throw new InvalidTerritoryData('Unicode normalization failed.');
        }

        return $normalized;
    }

    /** @return array<array-key, mixed> */
    private static function readJson(string $path): array
    {
        if (str_contains($path, '://') || ! is_file($path) || ! is_readable($path) || filesize($path) > 2_000_000) {
            throw new InvalidTerritoryData('Territory data must be a readable local JSON file no larger than 2 MB.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidTerritoryData('Could not read territory data.');
        }
        try {
            $decoded = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidTerritoryData('Territory data contains invalid JSON.', previous: $exception);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidTerritoryData('Territory data must be a JSON object.');
        }

        return $decoded;
    }

    /** @param array<array-key, mixed> $row
     * @param  list<string>  $keys
     */
    private static function requireKeys(array $row, array $keys): void
    {
        $actual = array_keys($row);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new InvalidTerritoryData('Territory data has missing or unexpected fields.');
        }
    }

    /** @return list<array<array-key, mixed>> */
    private static function rows(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new InvalidTerritoryData('Territory tables must be nonempty JSON lists.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new InvalidTerritoryData('Territory rows must be JSON objects.');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private static function code(mixed $value, int $width): string
    {
        if (! is_string($value) || preg_match('/^[0-9]{'.$width.'}$/D', $value) !== 1) {
            throw new InvalidTerritoryData('CUT codes must be fixed-width digit strings with leading zeros preserved.');
        }

        return $value;
    }

    private static function name(mixed $value): string
    {
        if (! is_string($value) || $value === '' || ! mb_check_encoding($value, 'UTF-8') || preg_match('/^\s|\s$|\p{C}|\x{FFFD}/u', $value) === 1) {
            throw new InvalidTerritoryData('Official names must be unpadded Unicode without control or replacement characters.');
        }

        return $value;
    }

    /** @param array<string, object> $index */
    private static function unique(array $index, string $key): void
    {
        if (isset($index[$key])) {
            throw new InvalidTerritoryData('Territory data contains duplicate CUT codes.');
        }
    }
}
