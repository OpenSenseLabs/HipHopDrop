<?hh

/**
 * @file
 * Contains \Drupal\KernelTests\Core\Routing\RouteProviderTest.
 */

namespace Drupal\KernelTests\Core\Routing;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\MatcherDumper;
use Drupal\Core\Routing\RouteProvider;
use Drupal\Core\State\State;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\Core\Routing\RoutingFixtures;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Confirm that the default route provider is working correctly.
 *
 * @group Routing
 */
class RouteProviderTest extends KernelTestBase {

  /**
   * Modules to enable.
   */
  public static $modules = ['url_alter_test', 'system'];

  /**
   * A collection of shared fixture data for tests.
   *
   * @var RoutingFixtures
   */
  protected $fixtures;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\MemoryBackend
   */
  protected $cache;

  /**
   * The inbound path processor.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   */
  protected $pathProcessor;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  protected function setUp() {
    parent::setUp();
    $this->fixtures = new RoutingFixtures();
    $this->state = new State(new KeyValueMemoryFactory());
    $this->currentPath = new CurrentPathStack(new RequestStack());
    $this->cache = new MemoryBackend('data');
    $this->pathProcessor = \Drupal::service('path_processor_manager');
    $this->cacheTagsInvalidator = \Drupal::service('cache_tags.invalidator');
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Readd the incoming path alias for these tests.
    if ($container->hasDefinition('path_processor_alias')) {
      $definition = $container->getDefinition('path_processor_alias');
      $definition->addTag('path_processor_inbound');
    }
  }

  protected function tearDown() {
    $this->fixtures->dropTables(Database::getConnection());

    parent::tearDown();
  }

  /**
   * Confirms that the correct candidate outlines are generated.
   */
  public function testCandidateOutlines() {

    $connection = Database::getConnection();
    $provider = new TestRouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $parts = array('node', '5', 'edit');

    $candidates = $provider->getCandidateOutlines($parts);

    $candidates = array_flip($candidates);

    $this->assertTrue(count($candidates) == 7, 'Correct number of candidates found');
    $this->assertTrue(array_key_exists('/node/5/edit', $candidates), 'First candidate found.');
    $this->assertTrue(array_key_exists('/node/5/%', $candidates), 'Second candidate found.');
    $this->assertTrue(array_key_exists('/node/%/edit', $candidates), 'Third candidate found.');
    $this->assertTrue(array_key_exists('/node/%/%', $candidates), 'Fourth candidate found.');
    $this->assertTrue(array_key_exists('/node/5', $candidates), 'Fifth candidate found.');
    $this->assertTrue(array_key_exists('/node/%', $candidates), 'Sixth candidate found.');
    $this->assertTrue(array_key_exists('/node', $candidates), 'Seventh candidate found.');
  }

  /**
   * Don't fail when given an empty path.
   */
  public function testEmptyPathCandidatesOutlines() {
    $provider = new TestRouteProvider(Database::getConnection(), $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');
    $candidates = $provider->getCandidateOutlines([]);
    $this->assertEqual(count($candidates), 0, 'Empty parts should return no candidates.');
  }

  /**
   * Confirms that we can find routes with the exact incoming path.
   */
  public function testExactPathMatch() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->dump();

    $path = '/path/one';

    $request = Request::create($path, 'GET');

    $routes = $provider->getRouteCollectionForRequest($request);

    foreach ($routes as $route) {
      $this->assertEqual($route->getPath(), $path, 'Found path has correct pattern');
    }
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  public function testOutlinePathMatch() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($this->fixtures->complexRouteCollection());
    $dumper->dump();

    $path = '/path/1/one';

    $request = Request::create($path, 'GET');

    $routes = $provider->getRouteCollectionForRequest($request);

    // All of the matching paths have the correct pattern.
    foreach ($routes as $route) {
      $this->assertEqual($route->compile()->getPatternOutline(), '/path/%/one', 'Found path has correct pattern');
    }

    $this->assertEqual(count($routes), 2, 'The correct number of routes was found.');
    $this->assertNotNull($routes->get('route_a'), 'The first matching route was found.');
    $this->assertNotNull($routes->get('route_b'), 'The second matching route was not found.');
  }

  /**
   * Confirms that a trailing slash on the request doesn't result in a 404.
   */
  public function testOutlinePathMatchTrailingSlash() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($this->fixtures->complexRouteCollection());
    $dumper->dump();

    $path = '/path/1/one/';

    $request = Request::create($path, 'GET');

    $routes = $provider->getRouteCollectionForRequest($request);

    // All of the matching paths have the correct pattern.
    foreach ($routes as $route) {
      $this->assertEqual($route->compile()->getPatternOutline(), '/path/%/one', 'Found path has correct pattern');
    }

    $this->assertEqual(count($routes), 2, 'The correct number of routes was found.');
    $this->assertNotNull($routes->get('route_a'), 'The first matching route was found.');
    $this->assertNotNull($routes->get('route_b'), 'The second matching route was not found.');
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  public function testOutlinePathMatchDefaults() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}', array(
      'value' => 'poink',
    )));

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);

      // All of the matching paths have the correct pattern.
      foreach ($routes as $route) {
        $this->assertEqual($route->compile()->getPatternOutline(), '/some/path', 'Found path has correct pattern');
      }

      $this->assertEqual(count($routes), 1, 'The correct number of routes was found.');
      $this->assertNotNull($routes->get('poink'), 'The first matching route was found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matching route found with default argument value.');
    }
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  public function testOutlinePathMatchDefaultsCollision() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}', array(
      'value' => 'poink',
    )));
    $collection->add('narf', new Route('/some/path/here'));

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);

      // All of the matching paths have the correct pattern.
      foreach ($routes as $route) {
        $this->assertEqual($route->compile()->getPatternOutline(), '/some/path', 'Found path has correct pattern');
      }

      $this->assertEqual(count($routes), 1, 'The correct number of routes was found.');
      $this->assertNotNull($routes->get('poink'), 'The first matching route was found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matching route found with default argument value.');
    }
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  public function testOutlinePathMatchDefaultsCollision2() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}', array(
      'value' => 'poink',
    )));
    $collection->add('narf', new Route('/some/path/here'));
    $collection->add('eep', new Route('/something/completely/different'));

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path/here';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);
      $routes_array = $routes->all();

      $this->assertEqual(count($routes), 2, 'The correct number of routes was found.');
      $this->assertEqual(array('narf', 'poink'), array_keys($routes_array), 'Ensure the fitness was taken into account.');
      $this->assertNotNull($routes->get('narf'), 'The first matching route was found.');
      $this->assertNotNull($routes->get('poink'), 'The second matching route was found.');
      $this->assertNull($routes->get('eep'), 'Non-matching route was not found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matching route found with default argument value.');
    }
  }

  /**
   * Confirms that we can find multiple routes that match the request equally.
   */
  public function testOutlinePathMatchDefaultsCollision3() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/{value}/path'));
    // Add a second route matching the same path pattern.
    $collection->add('poink2', new Route('/some/{object}/path'));
    $collection->add('narf', new Route('/some/here/path'));
    $collection->add('eep', new Route('/something/completely/different'));

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/over-there/path';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);
      $routes_array = $routes->all();

      $this->assertEqual(count($routes), 2, 'The correct number of routes was found.');
      $this->assertEqual(array('poink', 'poink2'), array_keys($routes_array), 'Ensure the fitness and name were taken into account in the sort.');
      $this->assertNotNull($routes->get('poink'), 'The first matching route was found.');
      $this->assertNotNull($routes->get('poink2'), 'The second matching route was found.');
      $this->assertNull($routes->get('eep'), 'Non-matching route was not found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matching route found with default argument value.');
    }
  }

  /**
   * Tests a route with a 0 as value.
   */
  public function testOutlinePathMatchZero() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $collection = new RouteCollection();
    $collection->add('poink', new Route('/some/path/{value}'));

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($collection);
    $dumper->dump();

    $path = '/some/path/0';

    $request = Request::create($path, 'GET');

    try {
      $routes = $provider->getRouteCollectionForRequest($request);

      // All of the matching paths have the correct pattern.
      foreach ($routes as $route) {
        $this->assertEqual($route->compile()->getPatternOutline(), '/some/path/%', 'Found path has correct pattern');
      }

      $this->assertEqual(count($routes), 1, 'The correct number of routes was found.');
    }
    catch (ResourceNotFoundException $e) {
      $this->fail('No matchout route found with 0 as argument value');
    }
  }

  /**
   * Confirms that an exception is thrown when no matching path is found.
   */
  public function testOutlinePathNoMatch() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($this->fixtures->complexRouteCollection());
    $dumper->dump();

    $path = '/no/such/path';

    $request = Request::create($path, 'GET');


    $routes = $provider->getRoutesByPattern($path);
    $this->assertFalse(count($routes), 'No path found with this pattern.');

    $collection = $provider->getRouteCollectionForRequest($request);
    $this->assertTrue(count($collection) == 0, 'Empty route collection found with this pattern.');
  }

  /**
   * Tests that route caching works.
   */
  public function testRouteCaching() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->addRoutes($this->fixtures->complexRouteCollection());
    $dumper->dump();

    // A simple path.
    $path = '/path/add/one';
    $request = Request::create($path, 'GET');
    $provider->getRouteCollectionForRequest($request);

    $cache = $this->cache->get('route:/path/add/one:');
    $this->assertEqual('/path/add/one', $cache->data['path']);
    $this->assertEqual([], $cache->data['query']);
    $this->assertEqual(3, count($cache->data['routes']));

    // A path with query parameters.
    $path = '/path/add/one?foo=bar';
    $request = Request::create($path, 'GET');
    $provider->getRouteCollectionForRequest($request);

    $cache = $this->cache->get('route:/path/add/one:foo=bar');
    $this->assertEqual('/path/add/one', $cache->data['path']);
    $this->assertEqual(['foo' => 'bar'], $cache->data['query']);
    $this->assertEqual(3, count($cache->data['routes']));

    // A path with placeholders.
    $path = '/path/1/one';
    $request = Request::create($path, 'GET');
    $provider->getRouteCollectionForRequest($request);

    $cache = $this->cache->get('route:/path/1/one:');
    $this->assertEqual('/path/1/one', $cache->data['path']);
    $this->assertEqual([], $cache->data['query']);
    $this->assertEqual(2, count($cache->data['routes']));

    // A path with a path alias.
    /** @var \Drupal\Core\Path\AliasStorageInterface $path_storage */
    $path_storage = \Drupal::service('path.alias_storage');
    $path_storage->save('/path/add/one', '/path/add-one');
    /** @var \Drupal\Core\Path\AliasManagerInterface $alias_manager */
    $alias_manager = \Drupal::service('path.alias_manager');
    $alias_manager->cacheClear();

    $path = '/path/add-one';
    $request = Request::create($path, 'GET');
    $provider->getRouteCollectionForRequest($request);

    $cache = $this->cache->get('route:/path/add-one:');
    $this->assertEqual('/path/add/one', $cache->data['path']);
    $this->assertEqual([], $cache->data['query']);
    $this->assertEqual(3, count($cache->data['routes']));
  }

  /**
   * Test RouteProvider::getRouteByName() and RouteProvider::getRoutesByNames().
   */
  public function testRouteByName() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->dump();

    $route = $provider->getRouteByName('route_a');
    $this->assertEqual($route->getPath(), '/path/one', 'The right route pattern was found.');
    $this->assertEqual($route->getMethods(), ['GET'], 'The right route method was found.');
    $route = $provider->getRouteByName('route_b');
    $this->assertEqual($route->getPath(), '/path/one', 'The right route pattern was found.');
    $this->assertEqual($route->getMethods(), ['PUT'], 'The right route method was found.');

    $exception_thrown = FALSE;
    try {
      $provider->getRouteByName('invalid_name');
    }
    catch (RouteNotFoundException $e) {
      $exception_thrown = TRUE;
    }
    $this->assertTrue($exception_thrown, 'Random route was not found.');

    $routes = $provider->getRoutesByNames(array('route_c', 'route_d', $this->randomMachineName()));
    $this->assertEqual(count($routes), 2, 'Only two valid routes found.');
    $this->assertEqual($routes['route_c']->getPath(), '/path/two');
    $this->assertEqual($routes['route_d']->getPath(), '/path/three');
  }

  /**
   * Ensures that the routing system is capable of extreme long patterns.
   */
  public function testGetRoutesByPatternWithLongPatterns() {
    $connection = Database::getConnection();
    $provider = new TestRouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);
    // This pattern has only 3 parts, so we will get candidates, but no routes,
    // even though we have not dumped the routes yet.
    $shortest = '/test/1/test2';
    $result = $provider->getRoutesByPattern($shortest);
    $this->assertEqual($result->count(), 0);
    $candidates = $provider->getCandidateOutlines(explode('/', trim($shortest, '/')));
    $this->assertEqual(count($candidates), 7);
    // A longer patten is not found and returns no candidates
    $path_to_test = '/test/1/test2/2/test3/3/4/5/6/test4';
    $result = $provider->getRoutesByPattern($path_to_test);
    $this->assertEqual($result->count(), 0);
    $candidates = $provider->getCandidateOutlines(explode('/', trim($path_to_test, '/')));
    $this->assertEqual(count($candidates), 0);

    // Add a matching route and dump it.
    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $collection = new RouteCollection();
    $collection->add('long_pattern', new Route('/test/{v1}/test2/{v2}/test3/{v3}/{v4}/{v5}/{v6}/test4'));
    $dumper->addRoutes($collection);
    $dumper->dump();

    $result = $provider->getRoutesByPattern($path_to_test);
    $this->assertEqual($result->count(), 1);
    // We can't compare the values of the routes directly, nor use
    // spl_object_hash() because they are separate instances.
    $this->assertEqual(serialize($result->get('long_pattern')), serialize($collection->get('long_pattern')), 'The right route was found.');
    // We now have a single candidate outline.
    $candidates = $provider->getCandidateOutlines(explode('/', trim($path_to_test, '/')));
    $this->assertEqual(count($candidates), 1);
    // Longer and shorter patterns are not found. Both are longer than 3, so
    // we should not have any candidates either. The fact that we do not
    // get any candidates for a longer path is a security feature.
    $longer = '/test/1/test2/2/test3/3/4/5/6/test4/trailing/more/parts';
    $result = $provider->getRoutesByPattern($longer);
    $this->assertEqual($result->count(), 0);
    $candidates = $provider->getCandidateOutlines(explode('/', trim($longer, '/')));
    $this->assertEqual(count($candidates), 1);
    $shorter = '/test/1/test2/2/test3';
    $result = $provider->getRoutesByPattern($shorter);
    $this->assertEqual($result->count(), 0);
    $candidates = $provider->getCandidateOutlines(explode('/', trim($shorter, '/')));
    $this->assertEqual(count($candidates), 0);
    // This pattern has only 3 parts, so we will get candidates, but no routes.
    // This result is unchanged by running the dumper.
    $result = $provider->getRoutesByPattern($shortest);
    $this->assertEqual($result->count(), 0);
    $candidates = $provider->getCandidateOutlines(explode('/', trim($shortest, '/')));
    $this->assertEqual(count($candidates), 7);
  }

  /**
   * Tests getRoutesPaged().
   */
  public function testGetRoutesPaged() {
    $connection = Database::getConnection();
    $provider = new RouteProvider($connection, $this->state, $this->currentPath, $this->cache, $this->pathProcessor, $this->cacheTagsInvalidator, 'test_routes');

    $this->fixtures->createTables($connection);
    $dumper = new MatcherDumper($connection, $this->state, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->dump();

    $fixture_routes = $this->fixtures->staticSampleRouteCollection();

    // Query all the routes.
    $routes = $provider->getRoutesPaged(0);
    $this->assertEqual(array_keys($routes), array_keys($fixture_routes));

    // Query non routes.
    $routes = $provider->getRoutesPaged(0, 0);
    $this->assertEqual(array_keys($routes), []);

    // Query a limited sets of routes.
    $routes = $provider->getRoutesPaged(1, 2);
    $this->assertEqual(array_keys($routes), array_slice(array_keys($fixture_routes), 1, 2));
  }

}

class TestRouteProvider extends RouteProvider {

  public function getCandidateOutlines(array $parts) {
    return parent::getCandidateOutlines($parts);
  }

}
