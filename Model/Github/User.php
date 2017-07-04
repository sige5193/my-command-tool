<?php
namespace Model\Github;
use Util\Model\ModelAbstract;
class User extends ModelAbstract {
    /** @param array $plan */
    public function set_plan( $plan ) {
        if ( isset($plan['name']) ) {
            $this->plan_name = $plan['name'];
        }
        if ( isset($plan['space']) ) {
            $this->plan_space = $plan['space'];
        }
        if ( isset($plan['private_repos']) ) {
            $this->plan_private_repos = $plan['private_repos'];
        }
    }
}