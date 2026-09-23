<?php

use Tests\TestCase;

/*
| Les tests Feature démarrent l'application. Ceux qui touchent la base
| déclarent eux-mêmes `uses(RefreshDatabase::class)` : ils tournent sur la
| base PostgreSQL `residences_test` (voir phpunit.xml), jamais sur la base
| de développement — Mon Gravier testait sur sa base de travail, et ses
| essais n'étaient ni reproductibles ni lançables en intégration continue.
*/

pest()->extend(TestCase::class)->in('Feature');
