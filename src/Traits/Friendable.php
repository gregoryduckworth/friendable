<?php

namespace GregoryDuckworth\Friendable\Traits;

use Config;
use GregoryDuckworth\Friendable\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Friendable
 */
trait Friendable
{

    /**
     * User has many Friends
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function friends()
    {
        return $this->belongsToMany(Config::get('auth.model'), 'friends_users', 'user_id', 'friend_id')
            ->withPivot('status');
    }

    /**
     * User has many confirmed Friends
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function confirmedFriends()
    {
        return $this->friends()->where('status', Status::CONFIRMED);
    }

    /**
     * User has many Friends pending approval
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function pendingFriends()
    {
        return $this->friends()->where('status', Status::PENDING);
    }

    /**
     * User has many friends awaiting their approval
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function awaitingApproval()
    {
        return $this->friends()->where('status', Status::AWAITING_APPROVAL);
    }

    /**
     * User wants to request a friend
     *
     * @param  Model $friend
     *
     */
    public function requestFriendship(Model $friend)
    {
        $this->friends()->attach($friend->id, ['status' => Status::PENDING]);
        $friend->pendingFriendship($this);
    }

    /**
     * Friend is attached but awaiting their approval
     *
     * @param  Model $friend
     *
     */
    public function pendingFriendship(Model $friend)
    {
        $this->friends()->attach($friend->id, ['status' => Status::AWAITING_APPROVAL]);
    }

    /**
     * Confirm the friendship
     *
     * @param  Model $friend
     *
     */
    public function confirmFriendship(Model $friend)
    {
        $this->confirmation($friend);
        $friend->confirmation($this);
    }

    /**
     * Method to update the pivot value
     *
     * @param  Model $friend
     */
    public function confirmation(Model $friend)
    {
        $friend = $this->friends()->where('friend_id', $friend->id)->first();
        $friend->pivot->update([
            'status' => Status::CONFIRMED,
        ]);
    }

    /**
     * Remove the friendship
     *
     * @param  Model $friend
     *
     */
    public function removeFriendship(Model $friend)
    {
        $this->removal($friend);
        $friend->removal($this);
    }

    /**
     * Method to detach the friendship
     *
     * @param  Model $friend
     */
    public function removal(Model $friend)
    {
        $this->friends()->detach($friend->id);
    }

    /**
     * Method to check the status of a friendship
     *
     * @param Model $friend
     */
    public function friendshipStatus(Model $friend)
    {
        return $this->friends()->where('friend_id', $friend->id)->select('status')->first();
    }

}
