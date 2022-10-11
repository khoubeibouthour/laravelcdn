<?php

namespace Publiux\laravelcdn;

use Illuminate\Support\Facades\Request;
use InvalidArgumentException;
use Publiux\laravelcdn\Contracts\CdnFacadeInterface;
use Publiux\laravelcdn\Contracts\CdnHelperInterface;
use Publiux\laravelcdn\Contracts\ProviderFactoryInterface;
use Publiux\laravelcdn\Exceptions\EmptyPathException;
use Publiux\laravelcdn\Providers\Contracts\ProviderInterface;
use Publiux\laravelcdn\Validators\CdnFacadeValidator;

/**
 * Class CdnFacade.
 *
 * @category
 *
 * @author  Mahmoud Zalt <mahmoud@vinelab.com>
 */
class CdnFacade implements CdnFacadeInterface {

	/** @var array */
	protected $configurations;

	/** @var ProviderFactoryInterface */
	protected $providerFactory;

	/**
	 * instance of the default provider object.
	 *
	 * @var ProviderInterface
	 */
	protected $provider;

	/** @var CdnHelperInterface */
	protected $helper;

	/** @var CdnFacadeValidator */
	protected $cdnFacadeValidator;

	/**
	 * Calls the provider initializer.
	 *
	 * @param  ProviderFactoryInterface  $providerFactory
	 * @param  CdnHelperInterface        $helper
	 * @param  CdnFacadeValidator        $cdFacadeValidator
	 */
	public function __construct(ProviderFactoryInterface $providerFactory, CdnHelperInterface $helper, CdnFacadeValidator $cdFacadeValidator) {
		$this->providerFactory = $providerFactory;
		$this->helper = $helper;
		$this->cdnFacadeValidator = $cdFacadeValidator;

		$this->init();
	}

	/**
	 * Read the configuration file and pass it to the provider factory
	 * to return an object of the default provider specified in the
	 * config file.
	 */
	private function init() {
		// return the configurations from the config file
		$this->configurations = $this->helper->getConfigurations();

		// return an instance of the corresponding Provider concrete according to the configuration
		$this->provider = $this->providerFactory->create($this->configurations);
	}

	/**
	 * this function will be called from the 'views' using the
	 * 'Cdn' facade {{Cdn::asset('')}} to convert the path into
	 * it's CDN url.
	 *
	 * @param             $path
	 * @param  bool|null  $overrideBypass
	 *
	 * @return mixed
	 */
	public function asset($path, ?bool $overrideBypass = null) {
		// if asset always append the public/ dir to the path (since the user should not add public/ to asset)
		return $this->generateUrl($path, 'public/', $overrideBypass);
	}

	/**
	 * check if package is surpassed or not then
	 * prepare the path before generating the url.
	 *
	 * @param             $path
	 * @param  string     $prepend
	 * @param  bool|null  $overrideBypass
	 *
	 * @return mixed
	 */
	private function generateUrl($path, $prepend = '', ?bool $overrideBypass = null) {
		// if the package is surpassed, then return the same $path
		// to load the asset from the localhost
		$bypass = $overrideBypass ?? (isset($this->configurations['bypass']) && $this->configurations['bypass']);
		if ($bypass) {
			return Request::root() . '/' . $path;
		}

		if (!isset($path)) {
			throw new EmptyPathException('Path does not exist.');
		}

		// Add version number
		//$path = str_replace(
		//    "build",
		//    $this->configurations['providers']['aws']['s3']['version'],
		//    $path
		//);

		// remove slashes from begging and ending of the path
		// and append directories if needed
		$clean_path = $prepend . $this->helper->cleanPath($path);

		// call the provider specific url generator
		return $this->provider->urlGenerator($clean_path);
	}

	/**
	 * this function will be called from the 'views' using the
	 * 'Cdn' facade {{Cdn::mix('')}} to convert the Laravel 5.4 webpack mix
	 * generated file path into it's CDN url.
	 *
	 * @param $path
	 *
	 * @return mixed
	 * @throws Exceptions\EmptyPathException
	 * @throws InvalidArgumentException
	 */
	public function mix($path) {
		static $manifest = null;
		if (is_null($manifest)) {
			$manifest = json_decode(file_get_contents(public_path('mix-manifest.json')), true);
		}

		if (isset($manifest['/' . $path])) {
			return $this->generateUrl($manifest['/' . $path], 'public/');
		}

		if (isset($manifest[$path])) {
			return $this->generateUrl($manifest[$path], 'public/');
		}

		throw new InvalidArgumentException("File {$path} not defined in asset manifest.");
	}

	/**
	 * this function will be called from the 'views' using the
	 * 'Cdn' facade {{Cdn::elixir('')}} to convert the elixir generated file path into
	 * it's CDN url.
	 *
	 * @param $path
	 *
	 * @return mixed
	 * @throws Exceptions\EmptyPathException
	 * @throws InvalidArgumentException
	 */
	public function elixir($path) {
		static $manifest = null;
		if (is_null($manifest)) {
			$manifest = json_decode(file_get_contents(public_path('build/rev-manifest.json')), true);
		}
		if (isset($manifest[$path])) {
			return $this->generateUrl('build/' . $manifest[$path], 'public/');
		}
		throw new InvalidArgumentException("File {$path} not defined in asset manifest.");
	}

	/**
	 * this function will be called from the 'views' using the
	 * 'Cdn' facade {{Cdn::path('')}} to convert the path into
	 * it's CDN url.
	 *
	 * @param $path
	 *
	 * @return mixed
	 * @throws Exceptions\EmptyPathException
	 */
	public function path($path) {
		return $this->generateUrl($path);
	}
}
