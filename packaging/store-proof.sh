#!/usr/bin/env bash
#
# store-proof.sh — assert, against a real Magento, the things this module claims
# that no unit test can reach.
#
# Run by bin/store-proof, which has already installed the module the way
# somebody else would, enabled it, and run setup:upgrade twice. See
# `bin/store-proof -h` for what this is given.
#
# WHAT THIS IS AIMED AT. Installing this module in a real store turned up three
# faults that every suite had passed: subscriptions and plugins that named
# virtual types, which Magento refuses; a product page whose scope was entered
# too late to matter; and entries added to another module's list from an area
# file, which replaces that list instead of adding to it, so the storefront lost
# Magento's own EAV read handler and GraphQL lost every core processor. Product
# names came back empty and nothing failed. All three are asserted here.
#
# Assertions are made in BOTH directions. Every read switch is off on install,
# so the store this runs against is a store where the module must change nothing
# at all, and proving it changed nothing is half the proof.

set -euo pipefail

MODULE_NAME="Kingletas_CatalogIndex"
WIRING="${STORE_PROOF_STORE}/local.d/store-proof-catalog-index-wiring.php"
READS="${STORE_PROOF_STORE}/local.d/store-proof-catalog-index-reads.php"
FLAGS="${STORE_PROOF_STORE}/local.d/store-proof-catalog-index-flags.php"
PRODUCT="${STORE_PROOF_STORE}/local.d/store-proof-catalog-index-product.php"
INVENTED_SKU="store-proof-catalog-index-$$"
invented=0
failures=0

step() { printf '    %s\n' "$*"; }
bad() { printf '    FAILED: %s\n' "$*" >&2; failures=$((failures + 1)); }

value() { $STORE_PROOF_SQL 2>&1 <<< "$1" | tail -1 | tr -d '[:space:]'; }

# Reads key=value out of a report line without a regex, so no sed dialect gets
# to decide whether a proof passes.
field() {
	local token
	for token in $1; do
		case "$token" in
			"$2"=*) printf '%s' "${token#*=}"; return 0 ;;
		esac
	done
	return 1
}

# The invented product and its source items are deleted through Magento, so
# its URL rewrites and inventory go with it; raw SQL is only the fallback.
cleanup() {
	if [ "$invented" = "1" ]; then
		$STORE_PROOF_PHP /app/local.d/store-proof-catalog-index-product.php delete "$INVENTED_SKU" >/dev/null 2>&1 \
			|| $STORE_PROOF_SQL <<< "DELETE FROM catalog_product_entity WHERE sku = '${INVENTED_SKU}';" >/dev/null 2>&1 \
			|| true
	fi
	rm -f "$WIRING" "$READS" "$FLAGS" "$PRODUCT"
}
trap cleanup EXIT

# --- what installing it built ------------------------------------------------

step "the module reports itself enabled"
if ! $STORE_PROOF_MAGENTO module:status "$MODULE_NAME" 2>&1 | grep -qi 'enabled'; then
	bad "Magento does not report ${MODULE_NAME} as enabled"
fi

# Named rather than counted: the change-log tables Magento adds for each indexer
# share the prefix, so a count says nothing about the schema.
step "its three tables were created"
for table in kingletas_catalog_index_state kingletas_catalog_index_schedule kingletas_catalog_index_parked_purge; do
	found="$(value "SELECT COUNT(*) FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '${table}';")"
	[ "$found" = "1" ] || bad "the ${table} table was not created"
done

step "Magento knows all four indexers"
indexers="$($STORE_PROOF_MAGENTO indexer:info 2>&1 || true)"
for id in product price stock category; do
	grep -q "kingletas_catalog_index_${id}" <<< "$indexers" \
		|| bad "Magento does not list the kingletas_catalog_index_${id} indexer"
done

# --- every read switch is off, so the module must change nothing -------------

# A catalogue module that starts serving pages from documents the moment it is
# installed is how somebody else's storefront goes wrong on upgrade day.
#
# Asked of Magento rather than of config:show, which prints nothing for a path
# whose only value is an XML default.
cat > "$FLAGS" <<-'PHP'
	<?php
	declare(strict_types=1);

	/**
	 * Prints each read switch's effective value on the default store view.
	 */

	require '/app/app/bootstrap.php';

	use Magento\Framework\App\Bootstrap;
	use Magento\Framework\App\Config\ScopeConfigInterface;
	use Magento\Store\Model\ScopeInterface;

	$config = Bootstrap::create(BP, $_SERVER)->getObjectManager()->get(ScopeConfigInterface::class);

	foreach (array_slice($argv, 1) as $path) {
	    $on = $config->isSetFlag('kingletas_catalog_index/' . $path, ScopeInterface::SCOPE_STORE, 1);
	    printf("%s=%d\n", $path, $on ? 1 : 0);
	}
PHP

step "every read switch is off on a fresh install"
switches=(pages/category_listing pages/search_listing pages/product_view pages/category_view
	pages/category_tree pages/linked_products pages/widget pages/graphql
	features/configurable_options features/configurable_attributes)
flags="$($STORE_PROOF_PHP /app/local.d/store-proof-catalog-index-flags.php "${switches[@]}")"
for switch in "${switches[@]}"; do
	case "$(grep "^${switch}=" <<< "$flags" || true)" in
		"${switch}=0") : ;;
		"") bad "Magento said nothing about ${switch}" ;;
		*) bad "${switch} is on after a fresh install, so installing the module changes pages" ;;
	esac
done

# --- the wiring Magento actually built ---------------------------------------

cat > "$WIRING" <<-'PHP'
	<?php
	declare(strict_types=1);

	/**
	 * Reports what the container built for this module, globally and on the storefront.
	 */

	require '/app/app/bootstrap.php';

	use Magento\Framework\App\Area;
	use Magento\Framework\App\Bootstrap;
	use Magento\Framework\App\State;
	use Magento\Framework\ObjectManager\ConfigLoaderInterface;

	$objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();

	// A subscription model must be a real class. Mview's config converter calls
	// class_implements() on this string while it parses the XML, which returns
	// false for a virtual type, and the in_array() around it raises a TypeError.
	$mustBeRealClasses = [
	    'Kingletas\CatalogIndex\Model\Mview\LinkFieldSubscription',
	    'Kingletas\CatalogIndex\Model\Mview\SuperAttributeSubscription',
	    'Kingletas\CatalogIndex\Model\Mview\SuperAttributeLabelSubscription',
	];

	$missing = [];
	foreach ($mustBeRealClasses as $class) {
	    if (!class_exists($class) || !is_subclass_of($class, \Magento\Framework\Mview\View\SubscriptionInterface::class)) {
	        $missing[] = $class;
	    }
	}

	printf("notclasses=%d %s\n", count($missing), implode(',', $missing) ?: '-');

	// The four indexers are the other half of the same rule. These ARE virtual
	// types, which is allowed because the indexer config converter makes no such
	// check, and the test is that each still resolves to something that indexes.
	$indexers = [
	    'Kingletas\CatalogIndex\Model\Indexer\ProductIndexer',
	    'Kingletas\CatalogIndex\Model\Indexer\PriceIndexer',
	    'Kingletas\CatalogIndex\Model\Indexer\StockIndexer',
	    'Kingletas\CatalogIndex\Model\Indexer\CategoryIndexer',
	];

	$unresolved = [];
	foreach ($indexers as $name) {
	    try {
	        $built = $objectManager->create($name);
	    } catch (\Throwable $e) {
	        $unresolved[] = $name;
	        continue;
	    }
	    if (!$built instanceof \Magento\Framework\Indexer\ActionInterface
	        || !$built instanceof \Magento\Framework\Mview\ActionInterface
	    ) {
	        $unresolved[] = $name;
	    }
	}

	printf("unresolved=%d %s\n", count($unresolved), implode(',', $unresolved) ?: '-');

	/**
	 * Every class name a built object carries, whether held as a string or an instance.
	 *
	 * @return string[]
	 */
	$names = static function (object $built): array {
	    $found = [];
	    $walk = static function ($value) use (&$walk, &$found): void {
	        if (is_array($value)) {
	            foreach ($value as $item) {
	                $walk($item);
	            }

	            return;
	        }
	        if (is_object($value)) {
	            $found[] = get_class($value);

	            return;
	        }
	        if (is_string($value) && str_contains($value, '\\')) {
	            $found[] = $value;
	        }
	    };

	    foreach ((new ReflectionObject($built))->getProperties() as $property) {
	        $property->setAccessible(true);
	        $walk($property->getValue($built));
	    }

	    return $found;
	};

	$lists = [
	    'attributepool' => \Magento\Framework\EntityManager\Operation\AttributePool::class,
	    'extensionpool' => \Magento\Framework\EntityManager\Operation\ExtensionPool::class,
	    'graphql' => \Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CompositeCollectionProcessor::class,
	];

	/**
	 * @return array<string, string[]>
	 */
	$snapshot = static function () use ($objectManager, $lists, $names): array {
	    $out = [];
	    foreach ($lists as $key => $class) {
	        $out[$key] = $names($objectManager->create($class));
	    }

	    return $out;
	};

	// Global first: once the storefront configuration is loaded there is no way
	// back to it in the same process.
	$global = $snapshot();

	$objectManager->get(State::class)->setAreaCode(Area::AREA_FRONTEND);
	$objectManager->configure(
	    $objectManager->get(ConfigLoaderInterface::class)->load(Area::AREA_FRONTEND)
	);
	$frontend = $snapshot();

	// The fault this catches: declaring these lists in an area file replaces them
	// rather than adding to them, so the storefront silently loses whatever
	// Magento put there and every page still renders.
	foreach ($lists as $key => $class) {
	    $lost = array_filter(
	        array_diff($global[$key], $frontend[$key]),
	        static fn (string $name): bool => str_starts_with($name, 'Magento\\')
	    );
	    $ours = array_filter(
	        $frontend[$key],
	        static fn (string $name): bool => str_starts_with($name, 'Kingletas\\')
	    );

	    printf(
	        "%s lost=%d ours=%d total=%d\n",
	        $key,
	        count($lost),
	        count($ours),
	        count($frontend[$key])
	    );
	}
PHP

step "reading what the container built"
wiring="$($STORE_PROOF_PHP /app/local.d/store-proof-catalog-index-wiring.php)"
printf '%s\n' "$wiring" | while IFS= read -r line; do step "  ${line}"; done

# A virtual type here is a TypeError during setup:upgrade, raised while the XML
# is parsed and before DI is ever consulted.
step "every mview subscription model is a real subscription class"
notclasses_line="$(grep '^notclasses=' <<< "$wiring" || true)"
if [ "$(field "$notclasses_line" notclasses)" != "0" ]; then
	bad "named as a subscription model and not a real Magento subscription: ${notclasses_line#* }"
fi

# The other half of the same rule: these four are virtual types on purpose,
# because nothing checks them at parse time, so the test is that they resolve.
step "all four indexer virtual types resolve to something that can index"
unresolved_line="$(grep '^unresolved=' <<< "$wiring" || true)"
if [ "$(field "$unresolved_line" unresolved)" != "0" ]; then
	bad "these indexers do not resolve to an indexer and an mview action: ${unresolved_line#* }"
fi

for list in attributepool extensionpool graphql; do
	line="$(grep "^${list} " <<< "$wiring" || true)"
	if [ -z "$line" ]; then
		bad "the wiring report said nothing about ${list}"
		continue
	fi

	step "the storefront's ${list} keeps what Magento put there"
	[ "$(field "$line" lost)" = "0" ] \
		|| bad "the storefront's ${list} is missing $(field "$line" lost) of Magento's own entries, so an area file has replaced the list instead of adding to it"

	step "and carries this module's own entry"
	if [ "$(field "$line" ours)" = "0" ]; then
		bad "this module contributes nothing to the storefront's ${list}, so it is not wired at all"
	fi
done

# --- with every switch off, a page is Magento's own answer -------------------

cat > "$READS" <<-'PHP'
	<?php
	declare(strict_types=1);

	/**
	 * Reads a product on the storefront and reports what the page would show.
	 */

	require '/app/app/bootstrap.php';

	use Magento\Framework\App\Area;
	use Magento\Framework\App\Bootstrap;
	use Magento\Framework\App\State;
	use Magento\Framework\ObjectManager\ConfigLoaderInterface;

	$objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
	$objectManager->get(State::class)->setAreaCode(Area::AREA_FRONTEND);
	$objectManager->configure(
	    $objectManager->get(ConfigLoaderInterface::class)->load(Area::AREA_FRONTEND)
	);

	$sku = $argv[1] ?? '';
	$product = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class)
	    ->get($sku, false, 1);

	// An empty name is what the replaced attribute pool produced, and nothing
	// anywhere failed when it did.
	printf(
	    "namelength=%d sku=%s attributes=%d\n",
	    strlen((string) $product->getName()),
	    (string) $product->getSku(),
	    count($product->getData())
	);
PHP

# A store with no catalogue gets one invented product, removed afterwards.
cat > "$PRODUCT" <<-'PHP'
	<?php
	declare(strict_types=1);

	/**
	 * Saves or deletes one invented simple product through Magento's own repository.
	 */

	require '/app/app/bootstrap.php';

	use Magento\Catalog\Api\Data\ProductInterfaceFactory;
	use Magento\Catalog\Api\ProductRepositoryInterface;
	use Magento\Catalog\Model\Product\Attribute\Source\Status;
	use Magento\Catalog\Model\Product\Visibility;
	use Magento\Framework\App\Area;
	use Magento\Framework\App\Bootstrap;
	use Magento\Framework\App\State;
	use Magento\Framework\Registry;

	[, $action, $sku] = $argv + [null, '', ''];

	$objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
	$objectManager->get(State::class)->setAreaCode(Area::AREA_ADMINHTML);
	$repository = $objectManager->get(ProductRepositoryInterface::class);

	if ($action === 'delete') {
	    $objectManager->get(Registry::class)->register('isSecureArea', true);
	    $repository->deleteById($sku);

	    // Inventory keeps a deleted product's source items unless it is set to synchronise with the catalogue.
	    if (interface_exists(\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class)) {
	        $items = $objectManager->get(\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class)->execute($sku);
	        if ($items !== []) {
	            $objectManager->get(\Magento\InventoryApi\Api\SourceItemsDeleteInterface::class)->execute($items);
	        }
	    }

	    printf("deleted=%s\n", $sku);
	    exit(0);
	}

	$product = $objectManager->get(ProductInterfaceFactory::class)->create();
	$product->setSku($sku)
	    ->setUrlKey($sku)
	    ->setName('Invented Proof Product')
	    ->setTypeId('simple')
	    ->setAttributeSetId(4)
	    ->setPrice(10.0)
	    ->setStatus(Status::STATUS_ENABLED)
	    ->setVisibility(Visibility::VISIBILITY_BOTH)
	    ->setWebsiteIds([1]);
	$repository->save($product);

	printf("saved=%s\n", $sku);
PHP

sku="$(value "SELECT sku FROM catalog_product_entity WHERE type_id = 'simple' ORDER BY entity_id LIMIT 1;")"
if [ -z "$sku" ]; then
	step "the store has no simple product, so one is invented"
	invented=1
	$STORE_PROOF_PHP /app/local.d/store-proof-catalog-index-product.php save "$INVENTED_SKU" >/dev/null \
		|| { echo "    could not save an invented product through the repository" >&2; exit 1; }
	sku="$INVENTED_SKU"
fi

step "reading product ${sku} on the storefront"
read_line="$($STORE_PROOF_PHP /app/local.d/store-proof-catalog-index-reads.php "$sku" | tail -1)"
step "  ${read_line}"

step "its name is not empty"
[ "$(field "$read_line" namelength)" != "0" ] \
	|| bad "the product came back with an empty name, which is what a replaced attribute pool does"

step "it is the product that was asked for"
[ "$(field "$read_line" sku)" = "$sku" ] \
	|| bad "asked for ${sku} and got $(field "$read_line" sku)"

# Attribute tables join on row_id where content staging is installed and on
# entity_id where it is not, so the column is asked for rather than assumed.
link_field="$(value "SELECT IF(COUNT(*) > 0, 'row_id', 'entity_id') FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'catalog_product_entity' AND COLUMN_NAME = 'row_id';")"

step "its name matches the database exactly"
db_name_length="$(value "SELECT CHAR_LENGTH(v.value) FROM catalog_product_entity_varchar v
	JOIN catalog_product_entity e ON e.${link_field} = v.${link_field}
	JOIN eav_attribute a ON a.attribute_id = v.attribute_id AND a.attribute_code = 'name'
	WHERE e.sku = '${sku}' AND v.store_id = 0 LIMIT 1;")"
if [ -n "$db_name_length" ] && [ "$db_name_length" != "$(field "$read_line" namelength)" ]; then
	bad "the storefront's name is $(field "$read_line" namelength) characters and the database holds ${db_name_length}"
fi

# --- an invented product leaves nothing behind ------------------------------

# Rewrites and source items are keyed by URL and SKU, not by a foreign key, so
# a raw delete would leave them and the next run's save would collide.
if [ "$invented" = "1" ]; then
	step "the invented product and its source items are deleted through Magento"
	if $STORE_PROOF_PHP /app/local.d/store-proof-catalog-index-product.php delete "$INVENTED_SKU" >/dev/null; then
		invented=0
	else
		bad "the repository could not delete the invented product ${INVENTED_SKU}"
	fi

	step "no invented product, rewrite or source item is left from this or any earlier run"
	left="$(value "SELECT COUNT(*) FROM catalog_product_entity WHERE sku LIKE 'store-proof-catalog-index-%';")"
	[ "$left" = "0" ] || bad "${left} invented product(s) are still in the catalogue"
	left="$(value "SELECT COUNT(*) FROM url_rewrite WHERE request_path LIKE 'store-proof-catalog-index-%';")"
	[ "$left" = "0" ] || bad "${left} URL rewrite(s) for an invented product are still there"
	msi="$(value "SELECT COUNT(*) FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_source_item';")"
	if [ "$msi" = "1" ]; then
		left="$(value "SELECT COUNT(*) FROM inventory_source_item WHERE sku LIKE 'store-proof-catalog-index-%';")"
		[ "$left" = "0" ] || bad "${left} inventory source item(s) for an invented product are still there"
	fi
fi

# --- verdict -----------------------------------------------------------------

if [ "$failures" -gt 0 ]; then
	printf '\n    %d assertion(s) failed\n' "$failures" >&2
	exit 1
fi

printf '    every assertion held\n'
