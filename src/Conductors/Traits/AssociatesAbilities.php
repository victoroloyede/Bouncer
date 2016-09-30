<?php

namespace Silber\Bouncer\Conductors\Traits;

use Silber\Bouncer\Database\Models;
use Silber\Bouncer\Database\Ability;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

trait AssociatesAbilities
{
    /**
     * Get the authority, creating a role authority if necessary.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getAuthority()
    {
        if ($this->authority instanceof Model) {
            return $this->authority;
        }

        return Models::role()->firstOrCreate(['name' => $this->authority]);
    }

    /**
     * Get the IDs of the provided abilities.
     *
     * @param  \Silber\Bouncer\Database\Ability|array|int  $abilities
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @param  bool  $onlyOwned
     * @return array
     */
    protected function getAbilityIds($abilities, $model, $onlyOwned)
    {
        if ($abilities instanceof Ability) {
            return [$abilities->getKey()];
        }

        if ( ! is_null($model)) {
            return [$this->getModelAbility($abilities, $model, $onlyOwned)->getKey()];
        }

        return $this->abilitiesByName($abilities)->pluck('id')->all();
    }

    /**
     * Get an ability for the given entity.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string  $entity
     * @param  bool  $onlyOwned
     * @return \Silber\Bouncer\Database\Ability
     */
    protected function getModelAbility($ability, $entity, $onlyOwned)
    {
        $entity = $this->getEntityInstance($entity);

        $existing = $this->findAbility($ability, $entity, $onlyOwned);

        return $existing ?: $this->createAbility($ability, $entity, $onlyOwned);
    }

    /**
     * Find the ability for the given entity.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string  $entity
     * @param  bool  $onlyOwned
     * @return \Silber\Bouncer\Database\Ability|null
     */
    protected function findAbility($ability, $entity, $onlyOwned)
    {
        return Models::ability()
                     ->where('name', $ability)
                     ->forModel($entity, true)
                     ->where('only_owned', $onlyOwned)
                     ->first();
    }

    /**
     * Create an ability for the given entity.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string  $entity
     * @param  bool  $onlyOwned
     * @return \Silber\Bouncer\Database\Ability
     */
    protected function createAbility($ability, $entity, $onlyOwned)
    {
        return Models::ability()->createForModel($entity, [
            'name' => $ability,
            'only_owned' => $onlyOwned,
        ]);
    }

    /**
     * Get an instance of the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @return \Illuminate\Database\Eloquent\Model|string
     */
    protected function getEntityInstance($model)
    {
        if ($model == '*') {
            return '*';
        }

        if ( ! $model instanceof Model) {
            return new $model;
        }

        // Creating an ability for a non-existent model gives the authority that
        // ability on all instances of that model. If the developer passed in
        // a model instance that does not exist, it is probably a mistake.
        if ( ! $model->exists) {
            throw new InvalidArgumentException(
                'The model does not exist. To allow access to all models, use the class name instead'
            );
        }

        return $model;
    }

    /**
     * Get or create abilities by their name.
     *
     * @param  array|string  $ability
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function abilitiesByName($ability)
    {
        $abilities = array_unique(is_array($ability) ? $ability : [$ability]);

        $models = Models::ability()->simpleAbility()->whereIn('name', $abilities)->get();

        $created = $this->createMissingAbilities($models, $abilities);

        return $models->merge($created);
    }

    /**
     * Create abilities whose name is not in the given list.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @param  array  $abilities
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function createMissingAbilities(Collection $models, array $abilities)
    {
        $missing = array_diff($abilities, $models->pluck('name')->all());

        $created = [];

        foreach ($missing as $ability) {
            $created[] = Models::ability()->create(['name' => $ability]);
        }

        return $created;
    }

    /**
     * Get the IDs of the associated abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  array  $abilityIds
     * @param  bool $forbidden
     * @return array
     */
    protected function getAssociatedAbilityIds(Model $authority, array $abilityIds, $forbidden)
    {
        return $authority->abilities()
                         ->whereIn('id', $abilityIds)
                         ->wherePivot('forbidden', '=', $forbidden)
                         ->get(['id'])->pluck('id')
                         ->all();
    }
}
