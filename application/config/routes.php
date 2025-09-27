<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// API Routes
$route['api/auth/login'] = 'auth/login';
$route['api/auth/register'] = 'auth/register';
$route['api/auth/me'] = 'auth/me';
$route['api/auth/refresh'] = 'auth/refresh';
$route['api/auth/logout'] = 'auth/logout';

// Role API Routes
$route['api/roles'] = 'role/index';
$route['api/roles/create'] = 'role/create';
$route['api/roles/(:num)'] = 'role/show/$1';
$route['api/roles/(:num)/update'] = 'role/update/$1';
$route['api/roles/(:num)/delete'] = 'role/delete/$1';

// User API Routes
$route['api/users'] = 'user/index';
$route['api/users/create'] = 'user/create';
$route['api/users/(:num)'] = 'user/show/$1';
$route['api/users/(:num)/update'] = 'user/update/$1';
$route['api/users/(:num)/delete'] = 'user/delete/$1';

// Cities API Routes
$route['api/cities'] = 'cities/index';
$route['api/cities/create'] = 'cities/create';
$route['api/cities/(:num)'] = 'cities/get/$1';
$route['api/cities/(:num)/update'] = 'cities/update/$1';
$route['api/cities/(:num)/delete'] = 'cities/delete/$1';

// Vendors API Routes
$route['api/vendors'] = 'vendors/index';
$route['api/vendors/create'] = 'vendors/create';
$route['api/vendors/(:num)'] = 'vendors/get/$1';
$route['api/vendors/(:num)/update'] = 'vendors/update/$1';
$route['api/vendors/(:num)/delete'] = 'vendors/delete/$1';

// DTO API Routes
$route['api/dto'] = 'dto/index';
$route['api/dto/create'] = 'dto/create';
$route['api/dto/(:num)'] = 'dto/get/$1';
$route['api/dto/(:num)/update'] = 'dto/update/$1';
$route['api/dto/(:num)/delete'] = 'dto/delete/$1';

// Applications API Routes
$route['api/applications'] = 'application/index';
$route['api/applications/create'] = 'application/create';
$route['api/applications/(:num)'] = 'application/show/$1';
$route['api/applications/(:num)/update'] = 'application/update/$1';
$route['api/applications/(:num)/delete'] = 'application/delete/$1';

// Reports API Routes
$route['api/reports'] = 'reports/index';
$route['api/reports/financial'] = 'reports/financial';
$route['api/reports/applications'] = 'reports/applications';
$route['api/reports/income'] = 'reports/income';
$route['api/reports/dues'] = 'reports/dues';
$route['api/reports/customers'] = 'reports/customers';
