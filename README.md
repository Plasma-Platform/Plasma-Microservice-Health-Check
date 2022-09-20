# microservice-health-checks module

Module have several built in health checks like db and cache


Add health check in configuration by populating checks array property

for example

```
'modules'=>[
        .........
        'healthchecks' => [
            'class' => 'templatemonster\healthchecks\Module',
            'checks'=> [
                'db',
                'cache',
                'mongodb',
                'Rabbitmq',
                'elasticsearch',
                'urls' => [
                    'name1' => 'http://ukr1',
                    'name2' => 'http://ukr2',
                ]
                ,
                'custom' => function() {
                    return (2 + 2 == 4);
                };
            ],
        ],
        .........
```