<?php

function makeResponse($status_code, $message = "", $data =[])
{
    
    if($status_code == 200 || $status_code == 201){
        $error = 0;
    }else{
        $error = 1;
    }
    return response()->json([
        'error' => $error,
        'status' => $status_code,
        'message' => $message,
        'data' => $data,
    ]);
}

function timestamp() {
    $dt = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    return $dt->format('Y-m-d H:i:s');
}

function displayDate($date){
    if(empty($date)) return '';
    return date('Y-m-d',strtotime($date));
}

if (!function_exists('can')) {
    /**
     * Check if the authenticated user has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    function can($permission)
    {
        if (!auth()->check()) {
            return false;
        }
        
        return auth()->user()->hasPermission($permission);
    }
}

if (!function_exists('hasRole')) {
    /**
     * Check if the authenticated user has a specific role.
     *
     * @param string $role
     * @return bool
     */
    function hasRole($role)
    {
        if (!auth()->check()) {
            return false;
        }
        
        return auth()->user()->hasRole($role);
    }
}

if (!function_exists('hasAnyRole')) {
    /**
     * Check if the authenticated user has any of the given roles.
     *
     * @param array|string $roles
     * @return bool
     */
    function hasAnyRole($roles)
    {
        if (!auth()->check()) {
            return false;
        }
        
        return auth()->user()->hasAnyRole($roles);
    }
}

if (!function_exists('hasAllRoles')) {
    /**
     * Check if the authenticated user has all of the given roles.
     *
     * @param array|string $roles
     * @return bool
     */
    function hasAllRoles($roles)
    {
        if (!auth()->check()) {
            return false;
        }
        
        return auth()->user()->hasAllRoles($roles);
    }
}

if (!function_exists('hasAnyPermission')) {
    /**
     * Check if the authenticated user has any of the given permissions.
     *
     * @param array|string $permissions
     * @return bool
     */
    function hasAnyPermission($permissions)
    {
        if (!auth()->check()) {
            return false;
        }
        
        return auth()->user()->hasAnyPermission($permissions);
    }
}

if (!function_exists('hasAllPermissions')) {
    /**
     * Check if the authenticated user has all of the given permissions.
     *
     * @param array|string $permissions
     * @return bool
     */
    function hasAllPermissions($permissions)
    {
        if (!auth()->check()) {
            return false;
        }
        
        return auth()->user()->hasAllPermissions($permissions);
    }
}