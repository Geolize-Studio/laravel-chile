<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile;

/**
 * Generated from the accepted subdere-cut-2018-v04 catalog.
 * Regenerate with python3 scripts/generate_territory_enums.py; do not edit manually.
 */
enum Province: string
{
    case Iquique = '011';
    case Tamarugal = '014';
    case Antofagasta = '021';
    case ElLoa = '022';
    case Tocopilla = '023';
    case Copiapo = '031';
    case Chanaral = '032';
    case Huasco = '033';
    case Elqui = '041';
    case Choapa = '042';
    case Limari = '043';
    case Valparaiso = '051';
    case IslaDePascua = '052';
    case LosAndes = '053';
    case Petorca = '054';
    case Quillota = '055';
    case SanAntonio = '056';
    case SanFelipeDeAconcagua = '057';
    case MargaMarga = '058';
    case Cachapoal = '061';
    case CardenalCaro = '062';
    case Colchagua = '063';
    case Talca = '071';
    case Cauquenes = '072';
    case Curico = '073';
    case Linares = '074';
    case Concepcion = '081';
    case Arauco = '082';
    case Biobio = '083';
    case Cautin = '091';
    case Malleco = '092';
    case Llanquihue = '101';
    case Chiloe = '102';
    case Osorno = '103';
    case Palena = '104';
    case Coyhaique = '111';
    case Aysen = '112';
    case CapitanPrat = '113';
    case GeneralCarrera = '114';
    case Magallanes = '121';
    case AntarticaChilena = '122';
    case TierraDelFuego = '123';
    case UltimaEsperanza = '124';
    case Santiago = '131';
    case Cordillera = '132';
    case Chacabuco = '133';
    case Maipo = '134';
    case Melipilla = '135';
    case Talagante = '136';
    case Valdivia = '141';
    case Ranco = '142';
    case Arica = '151';
    case Parinacota = '152';
    case Diguillin = '161';
    case Itata = '162';
    case Punilla = '163';

    public function code(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Iquique => 'Iquique',
            self::Tamarugal => 'Tamarugal',
            self::Antofagasta => 'Antofagasta',
            self::ElLoa => 'El Loa',
            self::Tocopilla => 'Tocopilla',
            self::Copiapo => 'Copiapó',
            self::Chanaral => 'Chañaral',
            self::Huasco => 'Huasco',
            self::Elqui => 'Elqui',
            self::Choapa => 'Choapa',
            self::Limari => 'Limarí',
            self::Valparaiso => 'Valparaíso',
            self::IslaDePascua => 'Isla de Pascua',
            self::LosAndes => 'Los Andes',
            self::Petorca => 'Petorca',
            self::Quillota => 'Quillota',
            self::SanAntonio => 'San Antonio',
            self::SanFelipeDeAconcagua => 'San Felipe de Aconcagua',
            self::MargaMarga => 'Marga Marga',
            self::Cachapoal => 'Cachapoal',
            self::CardenalCaro => 'Cardenal Caro',
            self::Colchagua => 'Colchagua',
            self::Talca => 'Talca',
            self::Cauquenes => 'Cauquenes',
            self::Curico => 'Curicó',
            self::Linares => 'Linares',
            self::Concepcion => 'Concepción',
            self::Arauco => 'Arauco',
            self::Biobio => 'Biobío',
            self::Cautin => 'Cautín',
            self::Malleco => 'Malleco',
            self::Llanquihue => 'Llanquihue',
            self::Chiloe => 'Chiloé',
            self::Osorno => 'Osorno',
            self::Palena => 'Palena',
            self::Coyhaique => 'Coyhaique',
            self::Aysen => 'Aysén',
            self::CapitanPrat => 'Capitán Prat',
            self::GeneralCarrera => 'General Carrera',
            self::Magallanes => 'Magallanes',
            self::AntarticaChilena => 'Antártica Chilena',
            self::TierraDelFuego => 'Tierra del Fuego',
            self::UltimaEsperanza => 'Última Esperanza',
            self::Santiago => 'Santiago',
            self::Cordillera => 'Cordillera',
            self::Chacabuco => 'Chacabuco',
            self::Maipo => 'Maipo',
            self::Melipilla => 'Melipilla',
            self::Talagante => 'Talagante',
            self::Valdivia => 'Valdivia',
            self::Ranco => 'Ranco',
            self::Arica => 'Arica',
            self::Parinacota => 'Parinacota',
            self::Diguillin => 'Diguillín',
            self::Itata => 'Itata',
            self::Punilla => 'Punilla',
        };
    }

    public function region(): Region
    {
        return match ($this) {
            self::Iquique => Region::Tarapaca,
            self::Tamarugal => Region::Tarapaca,
            self::Antofagasta => Region::Antofagasta,
            self::ElLoa => Region::Antofagasta,
            self::Tocopilla => Region::Antofagasta,
            self::Copiapo => Region::Atacama,
            self::Chanaral => Region::Atacama,
            self::Huasco => Region::Atacama,
            self::Elqui => Region::Coquimbo,
            self::Choapa => Region::Coquimbo,
            self::Limari => Region::Coquimbo,
            self::Valparaiso => Region::Valparaiso,
            self::IslaDePascua => Region::Valparaiso,
            self::LosAndes => Region::Valparaiso,
            self::Petorca => Region::Valparaiso,
            self::Quillota => Region::Valparaiso,
            self::SanAntonio => Region::Valparaiso,
            self::SanFelipeDeAconcagua => Region::Valparaiso,
            self::MargaMarga => Region::Valparaiso,
            self::Cachapoal => Region::OHiggins,
            self::CardenalCaro => Region::OHiggins,
            self::Colchagua => Region::OHiggins,
            self::Talca => Region::Maule,
            self::Cauquenes => Region::Maule,
            self::Curico => Region::Maule,
            self::Linares => Region::Maule,
            self::Concepcion => Region::Biobio,
            self::Arauco => Region::Biobio,
            self::Biobio => Region::Biobio,
            self::Cautin => Region::LaAraucania,
            self::Malleco => Region::LaAraucania,
            self::Llanquihue => Region::LosLagos,
            self::Chiloe => Region::LosLagos,
            self::Osorno => Region::LosLagos,
            self::Palena => Region::LosLagos,
            self::Coyhaique => Region::Aysen,
            self::Aysen => Region::Aysen,
            self::CapitanPrat => Region::Aysen,
            self::GeneralCarrera => Region::Aysen,
            self::Magallanes => Region::Magallanes,
            self::AntarticaChilena => Region::Magallanes,
            self::TierraDelFuego => Region::Magallanes,
            self::UltimaEsperanza => Region::Magallanes,
            self::Santiago => Region::Metropolitana,
            self::Cordillera => Region::Metropolitana,
            self::Chacabuco => Region::Metropolitana,
            self::Maipo => Region::Metropolitana,
            self::Melipilla => Region::Metropolitana,
            self::Talagante => Region::Metropolitana,
            self::Valdivia => Region::LosRios,
            self::Ranco => Region::LosRios,
            self::Arica => Region::AricaYParinacota,
            self::Parinacota => Region::AricaYParinacota,
            self::Diguillin => Region::Nuble,
            self::Itata => Region::Nuble,
            self::Punilla => Region::Nuble,
        };
    }

    /** @return list<Comuna> */
    public function comunas(): array
    {
        return array_values(array_filter(Comuna::cases(), fn (Comuna $comuna): bool => $comuna->province() === $this));
    }

    /** @return array<int|string, string> */
    public static function options(?Region $region = null): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            if ($region !== null && $case->region() !== $region) {
                continue;
            }

            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
