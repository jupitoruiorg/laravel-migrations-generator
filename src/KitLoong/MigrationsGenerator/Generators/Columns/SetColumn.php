<?php

namespace KitLoong\MigrationsGenerator\Generators\Columns;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use KitLoong\MigrationsGenerator\Generators\Blueprint\ColumnMethod;
use KitLoong\MigrationsGenerator\Repositories\MySQLRepository;

class SetColumn implements GeneratableColumn
{
    private $mysqlRepository;

    public function __construct(MySQLRepository $mySQLRepository)
    {
        $this->mysqlRepository = $mySQLRepository;
    }

    public function generate(string $type, Table $table, Column $column): ColumnMethod
    {
        $values = $this->mysqlRepository->getSetPresetValues($table->getName(), $column->getName());
        return new ColumnMethod($type, $column->getName(), $values);
    }
}
