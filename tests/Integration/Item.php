<?php

namespace Tests\Integration;

use Depotwarehouse\Toolbox\DataManagement\EloquentModels\BaseModel;

class Item extends \Depotwarehouse\Toolbox\DataManagement\EloquentModels\BaseModel {

    public $relatedModels = [
        'Tests\Integration\OtherItem' => 'oitem'
    ];

    protected $meta = [
        'id' => [ self::GUARDED ],
        'name' => [ self::FILLABLE, self::SEARCHABLE, self::UPDATEABLE ],
        'description' => [ self::FILLABLE, self::UPDATEABLE, self::SEARCHABLE ],
        'Tests\Integration\OtherItem:*' => [ self::SEARCHABLE ]
    ];

    public function __construct(array $attributes = array()) {
        parent::__construct($attributes);
    }

    public function oitem() {
        return $this->hasOne('Tests\Integration\OtherItem', 'item_id', 'id');
    }

}

class OtherItem extends \Depotwarehouse\Toolbox\DataManagement\EloquentModels\BaseModel {

    public $table = "oitems";

    public $relatedModels = [
        'Tests\Integration\ThirdItem' => 'titem'
    ];

    protected $meta = [
        'id' => [ self::GUARDED ],
        'item_id' => [ self::FILLABLE, self::UPDATEABLE ],
        'title' => [ self::FILLABLE, self::SEARCHABLE ],
        'Tests\Integration\ThirdItem:*' => [ self::SEARCHABLE ]
    ];

    public function __construct(array $attributes = array()) {
        parent::__construct($attributes);
    }

    public function titem() {
        return $this->hasOne('Tests\Integration\ThirdItem', 'oitem_id', 'id');
    }
}

class ThirdItem extends \Depotwarehouse\Toolbox\DataManagement\EloquentModels\BaseModel {

    public $table = "titems";

    protected $meta = [
        'id' => [ self::GUARDED ],
        'oitem_id' => [ self::FILLABLE, self::UPDATEABLE ],
        'slug' => [ self::FILLABLE, self::SEARCHABLE ],
        'Tests\Integration\Item:*' => [ self::SEARCHABLE ],
    ];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
    }

}

class ItemUninstantiableRelated extends BaseModel {

    protected $meta = [
        'Tests\Integration\ItemInterface:*' => [ self::SEARCHABLE ]
    ];

    public function __construct(array $attributes = array()) {
        parent::__construct();
    }

}

class ItemNotFoundRelated extends BaseModel {

    protected $meta = [
        'Tests\Integration\NotFoundClass:*' => [ self::SEARCHABLE ]
    ];

    public function __construct(array $attributes = array()) {
        parent::__construct();
    }

}

interface ItemInterface {

}