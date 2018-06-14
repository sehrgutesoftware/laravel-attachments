# Laravel Attachments

## Create attachment database column
Create a database field (nullable string) for every attachment of a model, e.g.:
```php
$table->string('profile_image')->nullable();
```

## Define the attachments in your model like this:
```php
protected $image_attachments = [
	'profile_image' => [
	    'path' => 'uploads/user/avatar',
	    'defaults' => [
	        'small' => '.jpg',
	        'medium' => '.jpg',
	        'large' => '.jpg',
	    ],
	    'styles' => [
	        'small' => '100',
	        'medium' => '500',
	        'large' => '1000'
	    ]
	],
	'background_image' => [
	    'path' => 'uploads/user/background',
	    'defaults' => [
	        'small' => '.jpg',
	        'medium' => '.jpg',
	        'large' => '.jpg',
	    ],
	    'styles' => [
	        'small' => '100',
	        'medium' => '500',
	        'large' => '1000'
	    ]
	]
];
```

## Using it
As simple as:
```php
$me->updateImageAttachment('profile_image', $request->fileContent(), $request->fileType());
```

