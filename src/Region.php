<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

/**
 * Generated from the accepted subdere-cut-2018-v04 catalog.
 * Regenerate with python3 scripts/generate_territory_enums.py; do not edit manually.
 */
enum Region: string
{
    case Tarapaca = '01';
    case Antofagasta = '02';
    case Atacama = '03';
    case Coquimbo = '04';
    case Valparaiso = '05';
    case OHiggins = '06';
    case Maule = '07';
    case Biobio = '08';
    case LaAraucania = '09';
    case LosLagos = '10';
    case Aysen = '11';
    case Magallanes = '12';
    case Metropolitana = '13';
    case LosRios = '14';
    case AricaYParinacota = '15';
    case Nuble = '16';

    public function code(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Tarapaca => 'Tarapacá',
            self::Antofagasta => 'Antofagasta',
            self::Atacama => 'Atacama',
            self::Coquimbo => 'Coquimbo',
            self::Valparaiso => 'Valparaíso',
            self::OHiggins => 'Libertador General Bernardo O\'Higgins',
            self::Maule => 'Maule',
            self::Biobio => 'Biobío',
            self::LaAraucania => 'La Araucanía',
            self::LosLagos => 'Los Lagos',
            self::Aysen => 'Aysén del General Carlos Ibáñez del Campo',
            self::Magallanes => 'Magallanes y de la Antártica Chilena',
            self::Metropolitana => 'Metropolitana de Santiago',
            self::LosRios => 'Los Ríos',
            self::AricaYParinacota => 'Arica y Parinacota',
            self::Nuble => 'Ñuble',
        };
    }

    /** @return list<Province> */
    public function provinces(): array
    {
        return array_values(array_filter(Province::cases(), fn (Province $province): bool => $province->region() === $this));
    }

    /** @return list<Comuna> */
    public function comunas(): array
    {
        return array_values(array_filter(Comuna::cases(), fn (Comuna $comuna): bool => $comuna->region() === $this));
    }

    /** @return array<int|string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
