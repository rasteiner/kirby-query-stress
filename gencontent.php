<?php
use Kirby\Cms\App as Kirby;
use Kirby\Cms\Pages;
use Kirby\Data\Data;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

require 'kirby/bootstrap.php';

$faker = Faker\Factory::create();

$kirby = new Kirby([
    'blueprints'
]);
$kirby->impersonate('kirby');

$site = $kirby->site();

function gen(string $type_plural, string $type_singular, string $faker_prop, int $amount) {
    global $faker;
    global $site;
        
    if($parent = $site->find($type_plural)) {
        $parent->delete(true);
    }

    $parent = $site->createChild([
        'slug' => $type_plural,
        'template' => 'entity',
        'content' => [
            'title' => ucfirst($type_plural),
            'allows_from' => 'person',
        ]
    ])->publish();

    echo "Generating $type_plural...\n";

    for($i = 0; $i < $amount; $i++) {
        $name = $faker->$faker_prop;

        try {
            $parent->createChild([
                'slug' => Str::slug($name),
                'template' => $type_singular,
                'content' => [
                    'title' => $name
                ]
            ])->publish();
        } catch (Exception $e) {
            $i -= 1;
        }
    }
}

function relate(string $type_a, string $type_b, array $relations, int $amount) {
    global $site;

    echo "Generating $amount relations for each of $type_a to $type_b...\n";

    $parent_a = $site->find($type_a);
    $parent_b = $site->find($type_b);

    $relationPages = new Pages();
    foreach ($relations as $relParent) {
        $relationPages->add($site->find("relations/$relParent")->children());
    }

    $possibleTargets = $parent_b->children()->shuffle()->pluck('id');
    $possibleTargets = new InfiniteIterator(new ArrayIterator($possibleTargets));

    foreach ($parent_a->children() as $from) {
        $fromRelations = $from->relations()->yaml();

        for($i = 0; $i < $amount; $i++) {
            $randomRelation = $relationPages->nth(rand(0, $relationPages->count() - 1));
            $target = $possibleTargets->current();
            $possibleTargets->next();
            
            $fromRelations[] = [
                'type' => $randomRelation->id(),
                'item' => $target
            ];
        }

        $yaml = Data::encode($fromRelations, 'yaml');
        $from->update([
            'relations' => $yaml
        ]);
    }
}

gen('people', 'person', 'name', 7000);
gen('companies', 'company', 'company', 1000);
gen('places', 'place', 'city', 200);

relate('people', 'people', ['parental', 'money'], 6);
relate('people', 'companies', ['money', 'work'], 4);
relate('people', 'places', ['location'], 8);
relate('companies', 'companies', ['money'], 8);
relate('companies', 'places', ['location'], 3);