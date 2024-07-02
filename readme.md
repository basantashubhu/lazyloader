# Lazy Loader

Lazy load Laravel Models with or without relationship.

Has Many :
```PHP
$usersWithClients = LazyLoader::make($users)->load(Client::class, 'clients')
    ->on([
        'clients.created_by' => 'id',
        'clients.assigned_to' => 'id'
    ])
    ->multi([
        'clients.email', 'clients.first_name', 'clients.last_name',
    ]);
```

Has One :

```PHP
$clientsWithUser = LazyLoader::make($clients)->load(User::class, 'user')
    ->on([
        'users.id' => [
            'created_by',
            'assigned_to'
        ]
    ])
    ->single([
        'users.id', 'users.first_name', 'users.last_name',
    ]);
```

Where  :

```PHP
$clientsWithUser = LazyLoader::make($clients)->load(User::class, 'user')
    ->on([
        'users.id' => [
            'created_by',
            'assigned_to'
        ]
    ])
    ->where('users.is_active', '=', 1)
    ->single([
        'users.id', 'users.first_name', 'users.last_name',
    ]);
```

When  :

```PHP
$clientsWithUser = LazyLoader::make($clients)->load(User::class, 'user')
    ->on([
        'users.id' => [
            'created_by',
            'assigned_to'
        ]
    ])
    ->when(function($item) {
        // custom logic
        // lazy load user model only when missing
        return empty($item['user']);
    })
    ->single([
        'users.id', 'users.first_name', 'users.last_name',
    ]);
```

