<?php declare(strict_types=1);

/**
 * This file is part of JWT Guardian, a PHP Experts, Inc., Project.
 *
 * Copyright © 2020 PHP Experts, Inc.
 * Author: Theodore R. Smith <theodore@phpexperts.pro>
 *   GPG Fingerprint: 4BF8 2613 1C34 87AC D28F  2AD8 EB24 A91D D612 5690
 *   https://www.phpexperts.pro/
 *   https://github.com/PHPExpertsInc/JWTGuardian
 *
 * This file is licensed under the MIT License.
 */

namespace PHPExperts\JWTGuardian;

use BadMethodCallException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Tymon\JWTAuth\JWT;

class JWTGuardian implements Guard
{
    use GuardHelpers;

    /**
     * The user we last attempted to retrieve.
     *
     * @var Authenticatable
     */
    protected $lastAttempted;

    /**
     * The JWT instance.
     *
     * @var JWT
     */
    protected $jwt;

    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Create a new authentication guard.
     */
    public function __construct(JWT $jwt, UserProvider $provider, Request $request)
    {
        $this->jwt = $jwt;
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Authenticatable|null
     */
    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        if ($this->jwt->setRequest($this->request)->getToken() && $this->jwt->check()) {
            $id = $this->jwt->payload()->get('sub');

            return $this->user = $this->provider->retrieveById($id);
        }

        return null;
    }

    /**
     * Log a user into the application without sessions or cookies.
     *
     * @return bool
     */
    public function once(array $credentials = [])
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->lastAttempted);

            return true;
        }

        return false;
    }

    /**
     * Validate a user's credentials.
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        return $this->attempt($credentials, false);
    }

    /**
     * Attempt to authenticate the user using the given credentials and return the token.
     *
     * @param bool $login
     *
     * @return mixed
     */
    public function attempt(array $credentials = [], $login = true)
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            return $login ? $this->login($user) : true;
        }

        return false;
    }

    /**
     * Create a token for a user.
     *
     * @return string
     */
    public function login(JWTSubject $user)
    {
        $this->setUser($user);

        return $this->jwt->fromUser($user);
    }

    /**
     * Log the given user ID into the application without sessions or cookies.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function onceUsingId($id)
    {
        if (!is_null($user = $this->provider->retrieveById($id))) {
            $this->setUser($user);

            return true;
        }

        return false;
    }

    /**
     * Logout the user.
     *
     * @param bool $forceForever
     *
     * @return bool
     */
    public function logout($forceForever = true)
    {
        $this->invalidate($forceForever);

        $this->user = null;
        $this->jwt->unsetToken();
    }

    /**
     * Generate new token by ID.
     *
     * @param mixed $id
     *
     * @return string|null
     */
    public function generateTokenById($id)
    {
        if (!is_null($user = $this->provider->retrieveById($id))) {
            return $this->jwt->fromUser($user);
        }
    }

    /**
     * Refresh current expired token.
     *
     * @return string
     */
    public function refresh()
    {
        return $this->requireToken()->refresh();
    }

    /**
     * Invalidate current token (add it to the blacklist).
     *
     * @param bool $forceForever
     *
     * @return bool
     */
    public function invalidate($forceForever = false)
    {
        return $this->requireToken()->invalidate($forceForever);
    }

    /**
     * Get the token.
     *
     * @return false|Token
     */
    public function getToken()
    {
        return $this->jwt->getToken();
    }

    /**
     * Set the token.
     *
     * @param Token|string $token
     *
     * @return JwtGuard
     */
    public function setToken($token)
    {
        $this->jwt->setToken($token);

        return $this;
    }

    /**
     * Get the raw Payload instance.
     *
     * @return \Tymon\JWTAuth\Payload
     */
    public function getPayload()
    {
        return $this->jwt->getPayload();
    }

    /**
     * Determine if the user matches the credentials.
     *
     * @param mixed $user
     * @param array $credentials
     *
     * @return bool
     */
    protected function hasValidCredentials($user, $credentials)
    {
        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Ensure that a token is available in the request.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *
     * @return \Tymon\JWTAuth\JWT
     */
    protected function requireToken()
    {
        if (!$this->getToken()) {
            throw new BadRequestHttpException('Token could not be parsed from the request.');
        }

        return $this->jwt;
    }

    /**
     * Get the last user we attempted to authenticate.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    public function getLastAttempted()
    {
        return $this->lastAttempted;
    }

    /**
     * Return the currently cached user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get the user provider used by the guard.
     *
     * @return \Illuminate\Contracts\Auth\UserProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Set the user provider used by the guard.
     *
     * @return $this
     */
    public function setProvider(UserProvider $provider)
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the current request instance.
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Magically call the JWT instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->jwt, $method)) {
            return call_user_func_array([$this->jwt, $method], $parameters);
        }

        throw new BadMethodCallException("Method [$method] does not exist.");
    }
}
