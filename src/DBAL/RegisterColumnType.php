<?php

namespace KitLoong\MigrationsGenerator\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use KitLoong\MigrationsGenerator\DBAL\Types\CustomType;
use KitLoong\MigrationsGenerator\DBAL\Types\DoubleType;
use KitLoong\MigrationsGenerator\DBAL\Types\EnumType;
use KitLoong\MigrationsGenerator\DBAL\Types\GeometryCollectionType;
use KitLoong\MigrationsGenerator\DBAL\Types\GeometryType;
use KitLoong\MigrationsGenerator\DBAL\Types\IpAddressType;
use KitLoong\MigrationsGenerator\DBAL\Types\JsonbType;
use KitLoong\MigrationsGenerator\DBAL\Types\LineStringType;
use KitLoong\MigrationsGenerator\DBAL\Types\LongTextType;
use KitLoong\MigrationsGenerator\DBAL\Types\MacAddressType;
use KitLoong\MigrationsGenerator\DBAL\Types\MediumIntegerType;
use KitLoong\MigrationsGenerator\DBAL\Types\MediumTextType;
use KitLoong\MigrationsGenerator\DBAL\Types\MultiLineStringType;
use KitLoong\MigrationsGenerator\DBAL\Types\MultiPointType;
use KitLoong\MigrationsGenerator\DBAL\Types\MultiPolygonType;
use KitLoong\MigrationsGenerator\DBAL\Types\PointType;
use KitLoong\MigrationsGenerator\DBAL\Types\PolygonType;
use KitLoong\MigrationsGenerator\DBAL\Types\SetType;
use KitLoong\MigrationsGenerator\DBAL\Types\TimestampType;
use KitLoong\MigrationsGenerator\DBAL\Types\TimestampTzType;
use KitLoong\MigrationsGenerator\DBAL\Types\TimeTzType;
use KitLoong\MigrationsGenerator\DBAL\Types\TinyIntegerType;
use KitLoong\MigrationsGenerator\DBAL\Types\Types;
use KitLoong\MigrationsGenerator\DBAL\Types\UUIDType;
use KitLoong\MigrationsGenerator\DBAL\Types\YearType;
use KitLoong\MigrationsGenerator\Enum\Driver;
use KitLoong\MigrationsGenerator\Repositories\PgSQLRepository;

class RegisterColumnType
{
    private $pgSQLRepository;

    public function __construct(PgSQLRepository $pgSQLRepository)
    {
        $this->pgSQLRepository = $pgSQLRepository;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function handle(): void
    {
        $this->registerLaravelColumnType();
        $this->registerLaravelCustomColumnType();

        $doctrineTypes = [
            Driver::MYSQL()->getValue()  => [
                'bit'            => Types::BOOLEAN,
                'geomcollection' => Types::GEOMETRY_COLLECTION,
                'json'           => Types::JSON,
                'mediumint'      => Types::MEDIUM_INTEGER,
                'tinyint'        => Types::TINY_INTEGER,
            ],
            Driver::PGSQL()->getValue()  => [
                '_int4'     => Types::TEXT,
                '_int8'     => Types::TEXT,
                '_numeric'  => Types::FLOAT,
                '_text'     => Types::TEXT,
                'cidr'      => Types::STRING,
                'geography' => Types::GEOMETRY,
                'inet'      => Types::IP_ADDRESS,
                'macaddr'   => Types::MAC_ADDRESS,
                'oid'       => Types::STRING,
            ],
            Driver::SQLITE()->getValue() => [],
            Driver::SQLSRV()->getValue() => [
                'geography'  => Types::GEOMETRY,
                'money'      => Types::DECIMAL,
                'smallmoney' => Types::DECIMAL,
                'tinyint'    => Types::TINY_INTEGER,
                'xml'        => Types::TEXT,
            ],
        ];

        // Register DB specific type, and fallback to Laravel column types.
        foreach ($doctrineTypes[DB::getDriverName()] as $dbType => $doctrineType) {
            $this->registerDoctrineTypeMapping($dbType, $doctrineType);
        }
    }

    /**
     * Register additional column types which are supported by the framework.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function registerLaravelColumnType(): void
    {
        /**
         * The map of supported doctrine mapping types.
         */
        $typeMap = [
            // [$name => $className]
            Types::DOUBLE              => DoubleType::class,
            Types::ENUM                => EnumType::class,
            Types::GEOMETRY            => GeometryType::class,
            Types::GEOMETRY_COLLECTION => GeometryCollectionType::class,
            Types::IP_ADDRESS          => IpAddressType::class,
            Types::JSONB               => JsonbType::class,
            Types::LINE_STRING         => LineStringType::class,
            Types::LONG_TEXT           => LongTextType::class,
            Types::MAC_ADDRESS         => MacAddressType::class,
            Types::MEDIUM_INTEGER      => MediumIntegerType::class,
            Types::MEDIUM_TEXT         => MediumTextType::class,
            Types::MULTI_LINE_STRING   => MultiLineStringType::class,
            Types::MULTI_POINT         => MultiPointType::class,
            Types::MULTI_POLYGON       => MultiPolygonType::class,
            Types::POINT               => PointType::class,
            Types::POLYGON             => PolygonType::class,
            Types::SET                 => SetType::class,
            Types::TIMESTAMP           => TimestampType::class,
            Types::TIMESTAMP_TZ        => TimestampTzType::class,
            Types::TIME_TZ             => TimeTzType::class,
            Types::TINY_INTEGER        => TinyIntegerType::class,
            Types::UUID                => UUIDType::class,
            Types::YEAR                => YearType::class,
        ];

        foreach ($typeMap as $dbType => $class) {
            $this->overrideDoctrineType($dbType, $class);
        }
    }

    /**
     * Register additional column types which are not supported by the framework.
     *
     * @note Uses {@see \Doctrine\DBAL\Types\Type::__construct} instead of {@see \Doctrine\DBAL\Types\Type::addType} here as workaround.
     * @return void
     * @throws \Doctrine\DBAL\Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) to suppress `getSQLDeclaration` warning.
     */
    private function registerLaravelCustomColumnType(): void
    {
        foreach ($this->getCustomTypes() as $type) {
            $customType       = new class () extends CustomType {
                public $type = '';

                public function getSQLDeclaration(array $column, AbstractPlatform $platform)
                {
                    return $this->type;
                }

                public function getName()
                {
                    return $this->type;
                }
            };
            $customType->type = $type;

            if (Type::hasType($type)) {
                continue;
            }

            Type::getTypeRegistry()->register($type, $customType);
            $this->registerDoctrineTypeMapping($type, $type);
        }
    }

    /**
     * Get a list of custom type names from DB.
     *
     * @return \Illuminate\Support\Collection<string>
     */
    private function getCustomTypes(): Collection
    {
        if (DB::getDriverName() === Driver::PGSQL()->getValue()) {
            return $this->pgSQLRepository->getCustomDataTypes();
        }

        return new Collection();
    }

    /**
     * Register custom doctrine type, override if exists.
     *
     * @param  string  $dbType
     * @param  string  $class  The class name of the custom type.
     * @throws \Doctrine\DBAL\Exception
     */
    private function overrideDoctrineType(string $dbType, string $class): void
    {
        $this->addOrOverrideType($dbType, $class);
        $this->registerDoctrineTypeMapping($dbType, $dbType);
    }

    /**
     * Add or override doctrine type.
     *
     * @param  string  $dbType
     * @param  string  $class  The class name of the custom type.
     * @throws \Doctrine\DBAL\Exception
     */
    private function addOrOverrideType(string $dbType, string $class): void
    {
        if (!Type::hasType($dbType)) {
            Type::addType($dbType, $class);
            return;
        }

        Type::overrideType($dbType, $class);
    }

    /**
     * Registers a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @param  string  $dbType
     * @param  string  $doctrineType
     * @throws \Doctrine\DBAL\Exception
     */
    private function registerDoctrineTypeMapping(string $dbType, string $doctrineType): void
    {
        DB::getDoctrineConnection()
            ->getDatabasePlatform()
            ->registerDoctrineTypeMapping($dbType, $doctrineType);
    }
}
