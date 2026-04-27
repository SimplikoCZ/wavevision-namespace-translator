<?php declare (strict_types = 1);

namespace Wavevision\NamespaceTranslator\Loaders;

use Nette\SmartObject;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use ReflectionClass;
use Wavevision\NamespaceTranslator\Exceptions\InvalidState;
use Wavevision\NamespaceTranslator\Exceptions\MissingResource;
use Wavevision\NamespaceTranslator\Exceptions\SkipResource;
use Wavevision\NamespaceTranslator\Loaders\TranslationClass\InjectLoadExport;
use Wavevision\NamespaceTranslator\Loaders\TranslationClass\InjectSaveResource;
use Wavevision\NamespaceTranslator\Resources\LocalePrefixPair;
use Wavevision\NamespaceTranslator\Resources\Translation;
use Wavevision\NamespaceTranslator\Transfer\InjectLocales;
use Wavevision\Utils\Arrays;
use Wavevision\Utils\Strings;
use function class_exists;
use function class_implements;
use function file_get_contents;
use function in_array;
use function is_file;
use function sprintf;
use function ucfirst;

class TranslationClass implements Loader
{

	use InjectHelpers;
	use InjectLoadExport;
	use InjectLocales;
	use InjectSaveResource;
	use SmartObject;

	public const FORMAT = 'php';

	/**
	 * @return array<mixed>
	 */
	public function load(string $resource): array
	{
		$class = $this->getClass($resource);
		/** @var Translation $class */
		return $class::define();
	}

	/**
	 * @inheritDoc
	 */
	public function getLocalePrefixPair(string $resourceName): LocalePrefixPair
	{
		$parts = Strings::split($resourceName, '/(?=[A-Z])/');
		return new LocalePrefixPair(Arrays::pop($parts), Arrays::implode($parts, ''));
	}

	public function fileSuffix(string $locale): string
	{
		return ucfirst($locale) . '.' . $this->getFileExtension();
	}

	/**
	 * @inheritDoc
	 */
	public function save(string $resource, array $content, ?string $referenceResource = null): void
	{
		$this->saveResource->save($resource, $content, $this->getFileExtension(), $referenceResource);
	}

	/**
	 * @inheritDoc
	 */
	public function loadExport(string $resource): array
	{
		$this->getClass($resource);
		return $this->loadExport->process($resource);
	}

	/**
	 * @inheritDoc
	 */
	public function saveKeyValue($key, string $value, array &$content): void
	{
		$this->helpers->buildTree($key, $value, $content);
	}

	public function getFileExtension(): string
	{
		return 'php';
	}

	private function getClass(string $resource): string
	{
		if (!is_file($resource)) {
			throw new MissingResource("Unable to read file '$resource'.");
		}
		$class = $this->findClassInFile($resource);
		if ($class === null) {
			throw new InvalidState("Unable to get translation class from '$resource'.");
		}
		if (!class_exists($class)) {
			require_once $resource;
		}
		if (!class_exists($class)) {
			throw new InvalidState("Translation class '$class' does not exist.");
		}
		// @phpstan-ignore-next-line
		if (!in_array(Translation::class, class_implements($class))) {
			throw new InvalidState(sprintf("Translation class '%s' must implement '%s'.", $class, Translation::class));
		}
		if ((new ReflectionClass($class))->isAbstract()) {
			throw new SkipResource();
		}
		return $class;
	}

	private function findClassInFile(string $file): ?string
	{
		$parser = (new ParserFactory())->createForNewestSupportedVersion();
		try {
			$stmts = $parser->parse(file_get_contents($file));
		} catch (\Throwable $e) {
			return null;
		}
		if ($stmts === null) {
			return null;
		}
		return $this->findClassInNodes($stmts);
	}

	/**
	 * @param Node[] $nodes
	 */
	private function findClassInNodes(array $nodes, ?string $namespace = null): ?string
	{
		foreach ($nodes as $node) {
			if ($node instanceof Namespace_) {
				return $this->findClassInNodes($node->stmts, $node->name ? $node->name->toString() : null);
			}
			if ($node instanceof Class_) {
				if ($node->name === null) {
					continue;
				}
				$name = $node->name->toString();
				return $namespace ? $namespace . '\\' . $name : $name;
			}
		}
		return null;
	}

}
