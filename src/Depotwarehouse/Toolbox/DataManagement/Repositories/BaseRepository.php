<?php

namespace Depotwarehouse\Toolbox\DataManagement\Repositories;

use Depotwarehouse\Toolbox\DataManagement\EloquentModels\BaseModel;
use Depotwarehouse\Toolbox\DataManagement\Validators\BaseValidatorInterface;
use Depotwarehouse\Toolbox\Exceptions\ParameterRequiredException;
use Depotwarehouse\Toolbox\Exceptions\ValidationException;

use Depotwarehouse\Toolbox\Verification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Eloquent;
use Config;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BaseRepository implements BaseRepositoryInterface {

    const OBJECT_CREATED = 201;
    const OBJECT_UPDATED = 202;


    /** @var BaseModel  */
    protected $model;

    /** @var \Depotwarehouse\Toolbox\DataManagement\Validators\BaseValidatorInterface  */
    protected $validator;


    public function __construct(BaseModel $model, BaseValidatorInterface $validator) {
        $this->model = $model;
        $this->validator = $validator;
    }


    /**
     * Returns all instances of the model
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->model->all();
    }

    public function filter($filters = array(), Callable $postFilter = null)
    {
        $items = $this->model->newQuery();
        foreach ($filters as $key => $value) {
            if (strpos($key, ':') === FALSE) {
                try {
                    $pair = Verification::getOpValuePair($value);
                    $items->where($key, $pair['op'], $pair['value']);
                } catch (ParameterRequiredException $exception) {
                    $items->where($key, $value);
                }
                continue;

            }
            $includePath = explode(':', $key);
            if (count($includePath) > 1) {
                $items->whereHas(array_shift($includePath), $this->buildIncludeFilter($includePath, $items, $value));
            }
        }

        if ($postFilter !== null) {
            $postFilter($items);
        }

        dd($items->toSql());

        return $items->paginate(Config::get('pagination.per_page'));
    }

    private function buildIncludeFilter(array &$includePath, Builder &$items, $value) {
        $current_include = array_shift($includePath);
        if (count($includePath) == 0) {
            return function ($query) use ($current_include, $value) {
                $query->where($current_include, $value);
            };
        }
        return function($query) use ($current_include, $includePath, $items, $value) {
            $query->whereHas($current_include, $this->buildIncludeFilter($includePath, $items, $value));
        };
    }

    /**
     * Searches all the searchable fields of the direct model (no related models) if they contain any of the array of terms.
     * Terms stack, eg. the function checks if any of the searchable fields match the first term AND any of the searchable fields
     * match the second term, etc.
     * @param array $terms Array of strings to search.
     * @return \Illuminate\Pagination\Paginator
     */
    public function search(array $terms = array()) {
        if (count($terms) == 0) {
            return $this->paginate();
        }

        $searchable_fields = $this->getSearchableFields(false);

        $items = $this->model->newQuery();

        foreach ($terms as $term) {
            $items->where(function($query) use ($searchable_fields, $term) {
                foreach ($searchable_fields as $searchable_field) {
                    $query->orWhere($searchable_field, 'LIKE', '%' . $term . '%');
                }
            });
        }
        return $items->paginate(Config::get('pagination.per_page'));
    }

    /**
     * Finds specific instances a model by ID(s)
     * @param $id string|int Either an integer ID or a comma separated string of IDs.
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|\Illuminate\Support\Collection|static
     */
    public function find($id)
    {
        $list = array_unique(explode(',', $id));
        sort($list);

        if (count($list) > 1) {
            $terms = new Collection();
            foreach ($list as $term_id) {
                $terms->push($this->model->findOrFail($term_id));
            }

            return $terms;
        }
        return $this->model->findOrFail($id);
    }

    /**
     * Creates a new instance of the model based on the array of attributes passed in
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model|static
     * @throws \Depotwarehouse\Toolbox\Exceptions\ValidationException
     */
    public function create(array $attributes)
    {
        try {
            $this->validator->validate($attributes);
        } catch (ValidationException $ex) {
            throw $ex;
        }

        // Todo is this enough?
        if (!array_key_exists("last_seen", $attributes) && in_array("last_seen", $this->model->fillable)) {
            $attributes['last_seen'] = Carbon::now()->toDateTimeString();
        }


        // todo catch excheptions here?
        $attributes = array_only($attributes, $this->getFillableFields());
        $model = $this->model->newInstance();
        $model->fill($attributes);
        $model->save();
        return $model;
    }



    /**
     * Updates a model with the given IDs using the array of attributes passed in.
     * If no attributes are passed in the model will be "touched" (updated_at set to now).
     * @param mixed $id unique identifier of the model
     * @param array $attributes the properties of the model to update as a key-value array
     * @return integer The status code of the outcome (either created or updated, as class constants)
     * @throws \Depotwarehouse\Toolbox\Exceptions\ValidationException
     * @throws \Exception
     */
    public function update($id, array $attributes = array())
    {
        try {
            $this->find($id);
        } catch (ModelNotFoundException $ex) {
            $this->create(array_merge([ 'id' => $id ], $attributes));
            return self::OBJECT_CREATED;
        }

        $attributes = array_only($attributes, $this->getUpdateableFields());

        try {
            $this->validator->updateValidate($attributes);
        } catch (ValidationException $ex) {
            throw $ex;
        }


        // todo catch exceptions here?
        $this->model->update($attributes);
        return self::OBJECT_UPDATED;
    }


    public function destroy($id)
    {
        return $this->model->destroy($id);
    }

    /**
     * @return \Illuminate\Pagination\Paginator
     */
    public function paginate()
    {
        return $this->model->paginate(Config::get('pagination.per_page'));
    }

    public function getFillableFields()
    {
        return $this->model->fillable;
    }

    /**
     * @return array list of updateable fields on the model
     */
    public function getUpdateableFields()
    {
        return $this->model->updateable;
    }

    /**
     * Retrieves a list of searchable fields on the model, and it's associated models.
     * @return array The list of searchable fields.
     */
    public function getSearchableFields($with_related = true)
    {
        $searchable = array();
        foreach ($this->model->searchable as $searchable_field) {
            // If we don't want related models, exclude everything with a colon
            if (!$with_related && strpos($searchable_field, ':') !== false) {
                continue;
            }
            $pos = strpos($searchable_field, '*');
            if ($pos !== false) {
                $key = substr($searchable_field, 0, $pos - 1);
                $model = new $this->model->relatedModels[$key];

                foreach ($model->searchable as $related_searchable) {
                    $searchable[] = $key . ":" . $related_searchable;
                }
            } else {
                $searchable[]= $searchable_field;
            }
        }
        return $searchable;
    }


}