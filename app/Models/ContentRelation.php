<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A link chosen in a relation field: owner item → related item (e.g. project → partner),
 * ordered by position within the field.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $field
 * @property string $related_type
 * @property int $related_id
 * @property int $position
 */
class ContentRelation extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'field', 'related_type', 'related_id', 'position'];
}
