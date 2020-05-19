<?php

declare(strict_types=1);

namespace AkeneoTest\Pim\Enrichment\Acceptance\Context;

use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\Product;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModel;
use Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\ProductNormalizer;
use Akeneo\Pim\Structure\Component\Model\AssociationType;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Model\Family;
use Akeneo\Pim\Structure\Component\Model\FamilyInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyVariant;
use Akeneo\Pim\Structure\Component\Model\FamilyVariantInterface;
use Akeneo\Pim\Structure\Component\Model\VariantAttributeSet;
use Akeneo\Test\Acceptance\AssociationType\InMemoryAssociationTypeRepository;
use Akeneo\Test\Acceptance\Product\InMemoryProductRepository;
use Akeneo\Test\Acceptance\ProductModel\InMemoryProductModelRepository;
use Akeneo\Test\Common\Structure\Attribute;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Behat\Behat\Context\Context;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

final class QuantifiedAssociationsContext implements Context
{
    /** @var Product|null */
    private $product;

    /** @var ProductModel|null */
    private $productModel;

    /** @var FamilyInterface|null */
    private $family;

    /** @var FamilyVariantInterface|null */
    private $familyVariant;

    /** @var AttributeInterface|null */
    private $attribute;

    /* --- */

    /** @var ValidatorInterface */
    private $validator;

    /** @var ObjectUpdaterInterface */
    private $productUpdater;

    /** @var ObjectUpdaterInterface */
    private $productModelUpdater;

    /** @var ProductNormalizer */
    private $standardProductNormalizer;

    /** @var InMemoryProductRepository */
    private $productRepository;

    /** @var InMemoryAssociationTypeRepository */
    private $associationTypeRepository;

    /** @var InMemoryProductModelRepository */
    private $productModelRepository;

    public function __construct(
        ValidatorInterface $validator,
        ObjectUpdaterInterface $productUpdater,
        ObjectUpdaterInterface $productModelUpdater,
        ProductNormalizer $standardProductNormalizer,
        InMemoryProductRepository $productRepository,
        InMemoryProductModelRepository $productModelRepository,
        InMemoryAssociationTypeRepository $associationTypeRepository
    ) {
        $this->validator = $validator;
        $this->productUpdater = $productUpdater;
        $this->productModelUpdater = $productModelUpdater;
        $this->standardProductNormalizer = $standardProductNormalizer;
        $this->productRepository = $productRepository;
        $this->productModelRepository = $productModelRepository;
        $this->associationTypeRepository = $associationTypeRepository;
    }

    private function createProduct(array $fields): Product
    {
        $product = new Product();
        $this->updateProduct($product, $fields);

        return $product;
    }

    private function createProductVariant(array $fields): Product
    {
        $product = new Product();
        $this->updateProduct($product, $fields);

        $product->setFamily($this->getFamily());
        $product->setFamilyVariant($this->getFamilyVariant());

        return $product;
    }

    private function updateProduct(Product $product, array $fields): void
    {
        $this->productUpdater->update($product, $fields);
    }

    private function validateEntityWithValues(EntityWithValuesInterface $entity): ConstraintViolationListInterface
    {
        return $this->validator->validate($entity);
    }

    private function createProductModel(array $fields): ProductModel
    {
        $productModel = new ProductModel();
        $this->updateProductModel($productModel, $fields);

        $productModel->setFamilyVariant($this->getFamilyVariant());

        return $productModel;
    }

    private function updateProductModel(ProductModel $productModel, array $fields): void
    {
        $this->productModelUpdater->update($productModel, $fields);
    }

    private function getAttribute(): AttributeInterface
    {
        if (null === $this->attribute) {
            $this->attribute = (new Attribute\Builder())->aIdentifier()
                ->withCode('sku')
                ->build();
        }

        return $this->attribute;
    }

    private function getFamily(): FamilyInterface
    {
        if (null === $this->family) {
            $this->family = new Family();
            $this->family->setCode('furniture');
            $this->family->addAttribute($this->getAttribute());
        }

        return $this->family;
    }

    private function getFamilyVariant(): FamilyVariantInterface
    {
        if (null === $this->familyVariant) {
            $this->familyVariant = new FamilyVariant();
            $this->familyVariant->setFamily($this->getFamily());

            $variantAttributeSet = new VariantAttributeSet();
            $variantAttributeSet->setLevel(1);
            $variantAttributeSet->addAttribute($this->getAttribute());
            $this->familyVariant->addVariantAttributeSet($variantAttributeSet);
        }

        return $this->familyVariant;
    }

    private function createAndPersistProductWithIdentifier(string $identifier): void
    {
        $this->productRepository->save($this->createProduct([
            'values' => [
                'sku' => [
                    [
                        'scope' => null,
                        'locale' => null,
                        'data' => $identifier,
                    ],
                ],
            ],
        ]));
    }

    private function createAndPersistProductModelWithCode(string $code): void
    {
        $this->productModelRepository->save($this->createProductModel([
            'code' => $code,
        ]));
    }

    private function createAndPersistQuantifiedAssociationType(string $code): void
    {
        $associationType = new AssociationType();
        $associationType->setCode($code);
        $associationType->setIsQuantified(true);

        $this->associationTypeRepository->save($associationType);
    }

    private function createAndPersistNormalAssociationType(string $code): void
    {
        $associationType = new AssociationType();
        $associationType->setCode($code);
        $associationType->setIsQuantified(false);

        $this->associationTypeRepository->save($associationType);
    }

    /**
     * @Given /^a product without quantified associations$/
     */
    public function aProductWithoutQuantifiedAssociations()
    {
        $this->product = $this->createProduct([
            'values' => [
                'sku' => [
                    [
                        'scope' => null,
                        'locale' => null,
                        'data' => 'yellow_chair',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @Given /^a product variant without quantified associations$/
     */
    public function aProductVariantWithoutQuantifiedAssociations()
    {
        $this->product = $this->createProductVariant([
            'values' => [
                'sku' => [
                    [
                        'scope' => null,
                        'locale' => null,
                        'data' => 'yellow_chair',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @Then /^this product should be associated to this other product$/
     */
    public function thisProductShouldBeAssociatedToThisOtherProduct()
    {
        $actualQuantifiedAssociations = $this->product->normalizeQuantifiedAssociations();
        $expectedQuantifiedAssociations = [
            'PACK' => [
                'products' => [
                    ['identifier' => 'accessory', 'quantity' => 42],
                ],
                'product_models' => [],
            ],
        ];

        Assert::same($actualQuantifiedAssociations, $expectedQuantifiedAssociations);
    }

    /**
     * @When /^I associate a product to this product with a quantity$/
     */
    public function iAssociateAProductToThisProductWithAQuantity()
    {
        $this->createAndPersistProductWithIdentifier('accessory');

        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => [
                        ['identifier' => 'accessory', 'quantity' => 42],
                    ],
                    'product_models' => [],
                ],
            ],
        ];

        $this->updateProduct($this->product, $fields);
        Assert::count($this->validateEntityWithValues($this->product), 0);
    }

    /**
     * @Given /^a product model without quantified associations$/
     */
    public function aProductModelWithoutQuantifiedAssociations()
    {
        $this->productModel = $this->createProductModel([
            'code' => 'standard_chair',
        ]);
    }

    /**
     * @When /^I associate a product to this product model with a quantity$/
     */
    public function iAssociateAProductToThisProductModelWithAQuantity()
    {
        $this->createAndPersistProductWithIdentifier('accessory');

        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => [
                        ['identifier' => 'accessory', 'quantity' => 42],
                    ],
                    'product_models' => [],
                ],
            ],
        ];

        $this->updateProductModel($this->productModel, $fields);
        Assert::count($this->validateEntityWithValues($this->productModel), 0);
    }

    /**
     * @Then /^this product model should be associated to this other product$/
     */
    public function thisProductModelShouldBeAssociatedToThisOtherProduct()
    {
        $actualQuantifiedAssociations = $this->productModel->normalizeQuantifiedAssociations();
        $expectedQuantifiedAssociations = [
            'PACK' => [
                'products' => [
                    ['identifier' => 'accessory', 'quantity' => 42],
                ],
                'product_models' => [],
            ],
        ];

        Assert::same($actualQuantifiedAssociations, $expectedQuantifiedAssociations);
    }

    /**
     * @Given /^this product has a parent with a quantified associations$/
     */
    public function thisProductHasAParentWithAQuantifiedAssociations()
    {
        $productModel = $this->createProductModel([
            'code' => 'standard_chair',
            'quantified_associations' => [
                'PACK' => [
                    'products' => [
                        ['identifier' => 'accessory', 'quantity' => 66],
                        ['identifier' => 'something_else', 'quantity' => 2],
                    ],
                    'product_models' => [],
                ],
            ],
        ]);

        $this->product->setParent($productModel);
    }

    /**
     * @When /^I add the same quantified association with a different quantity$/
     */
    public function iAddTheSameQuantifiedAssociationWithADifferentQuantity()
    {
        $this->createAndPersistProductWithIdentifier('accessory');

        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => [
                        ['identifier' => 'accessory', 'quantity' => 42],
                    ],
                    'product_models' => [],
                ],
            ],
        ];

        $this->updateProduct($this->product, $fields);
        Assert::count($this->validateEntityWithValues($this->product), 0);
    }

    /**
     * @Then /^this product should have this quantified association and all the other parent quantified associations$/
     */
    public function thisProductShouldHaveThisQuantifiedAssociationAndAllTheOtherParentQuantifiedAssociations()
    {
        $normalizedProduct = $this->standardProductNormalizer->normalize($this->product, 'standard');

        $actualQuantifiedAssociations = $normalizedProduct['quantified_associations'];
        $expectedQuantifiedAssociations = [
            'PACK' => [
                'products' => [
                    ['identifier' => 'accessory', 'quantity' => 42],
                    ['identifier' => 'something_else', 'quantity' => 2],
                ],
                'product_models' => [],
            ],
        ];

        Assert::same($actualQuantifiedAssociations, $expectedQuantifiedAssociations);
    }

    /**
     * @When /^I associate a product model to this product model with a quantity$/
     */
    public function iAssociateAProductModelToThisProductModelWithAQuantity()
    {
        $this->createAndPersistProductModelWithCode('accessory');

        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => [],
                    'product_models' => [
                        ['identifier' => 'accessory', 'quantity' => 42],
                    ],
                ],
            ],
        ];

        $this->updateProductModel($this->productModel, $fields);
        Assert::count($this->validateEntityWithValues($this->productModel), 0);
    }

    /**
     * @Then /^this product model should be associated to this other product model$/
     */
    public function thisProductModelShouldBeAssociatedToThisOtherProductModel()
    {
        $actualQuantifiedAssociations = $this->productModel->normalizeQuantifiedAssociations();
        $expectedQuantifiedAssociations = [
            'PACK' => [
                'products' => [],
                'product_models' => [
                    ['identifier' => 'accessory', 'quantity' => 42],
                ],
            ],
        ];

        Assert::same($actualQuantifiedAssociations, $expectedQuantifiedAssociations);
    }

    /**
     * @Given /^a quantified association type "([^"]*)"$/
     */
    public function aQuantifiedAssociationType($code)
    {
        $this->createAndPersistQuantifiedAssociationType($code);
    }

    /**
     * @Then /^there is the validation error "([^"]*)"$/
     */
    public function thereIsTheValidationError($message)
    {
        /** @var ConstraintViolationListInterface $violations */
        $violations = $this->validator->validate($this->product);

        $violationsMessages = [];

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $violationsMessages[] = $violation->getMessage();
        }

        Assert::true(in_array($message, $violationsMessages), sprintf(
            'The validation error "%s" was not found, got "%s"',
            $message,
            implode(',', $violationsMessages)
        ));
    }

    /**
     * @Given /^a product with a quantified link where the association type does not exist$/
     */
    public function aProductWithAQuantifiedLinkWhereTheAssociationTypeDoesNotExist(): void
    {
        $this->product = $this->createProduct([
            'values' => [
                'sku' => [
                    [
                        'scope' => null,
                        'locale' => null,
                        'data' => 'yellow_chair',
                    ],
                ],
            ],
            'quantified_associations' => [
                'INVALID_ASSOCIATION_TYPE' => [
                    'products' => [],
                    'product_models' => [],
                ],
            ],
        ]);
    }

    /**
     * @Given /^a product with a quantified link where the association type is not quantified$/
     */
    public function aProductWithAQuantifiedLinkWhereTheAssociationTypeIsNotQuantified()
    {
        $this->createAndPersistNormalAssociationType('XSELL');

        $this->product = $this->createProduct([
            'values' => [
                'sku' => [
                    [
                        'scope' => null,
                        'locale' => null,
                        'data' => 'yellow_chair',
                    ],
                ],
            ],
            'quantified_associations' => [
                'XSELL' => [
                    'products' => [],
                    'product_models' => [],
                ],
            ],
        ]);
    }

    /**
     * @When /^I associate a product to this product with the quantity "([^"]*)"$/
     */
    public function iAssociateAProductToThisProductWithTheQuantity($quantity)
    {
        $this->createAndPersistProductWithIdentifier('accessory');
        $this->createAndPersistQuantifiedAssociationType('PACK');

        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => [
                        ['identifier' => 'accessory', 'quantity' => (int)$quantity],
                    ],
                    'product_models' => [],
                ],
            ],
        ];

        $this->updateProduct($this->product, $fields);
    }

    /**
     * @When /^I associate a nonexistent product to this product with a quantity$/
     */
    public function iAssociateANonExistentProductToThisProductWithAQuantity()
    {
        $this->createAndPersistQuantifiedAssociationType('PACK');
        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => [
                        ['identifier' => 'accessory', 'quantity' => 1],
                    ],
                    'product_models' => [],
                ],
            ],
        ];

        $this->updateProduct($this->product, $fields);
    }

    /**
     * @When /^I associate a nonexistent product model to this product with a quantity$/
     */
    public function iAssociateANonExistentProductModelToThisProductWithAQuantity()
    {
        $this->createAndPersistQuantifiedAssociationType('PACK');
        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => [],
                    'product_models' => [
                        ['identifier' => 'accessory', 'quantity' => 42],
                    ],
                ],
            ],
        ];

        $this->updateProduct($this->product, $fields);
    }

    /**
     * @When /^I associate "([^"]*)" products with a quantity to this product$/
     */
    public function iAssociateProductsWithAQuantityToThisProduct(string $numberOfAssociation)
    {
        $productAssociations = [];
        for ($i = 0; $i < intval($numberOfAssociation); $i++) {
            $productIdentifier = "product-$i";
            $this->createAndPersistProductWithIdentifier($productIdentifier);

            $productAssociations[] = ['identifier' => $productIdentifier, 'quantity' => 42];
        }

        $this->createAndPersistQuantifiedAssociationType('PACK');
        $fields = [
            'quantified_associations' => [
                'PACK' => [
                    'products' => $productAssociations,
                    'product_models' => [],
                ],
            ],
        ];

        $this->updateProduct($this->product, $fields);
    }

    /**
     * @Given /^a product with invalid quantified link type$/
     */
    public function aProductWithInvalidQuantifiedLinkType()
    {
        $this->createAndPersistQuantifiedAssociationType('PACK');

        $this->product = $this->createProduct([
            'values' => [
                'sku' => [
                    [
                        'scope' => null,
                        'locale' => null,
                        'data' => 'yellow_chair',
                    ],
                ],
            ],
            'quantified_associations' => [
                'PACK' => [
                    'products' => [],
                    'product_drafts' => [],
                    'product_models' => [],
                ],
            ],
        ]);
    }

    /**
     * @Then /^the product is valid$/
     */
    public function theProductIsValid()
    {
        $violations = $this->validator->validate($this->product);
        Assert::count($violations, 0);
    }

    /**
     * @Given /^a product model with an invalid quantified association$/
     */
    public function aProductModelWithAnInvalidQuantifiedAssociation()
    {
        $this->productModel = $this->createProductModel([
            'code' => 'standard_chair',
            'quantified_associations' => [
                'INVALID_ASSOCIATION_TYPE' => [
                    'products' => [
                        ['identifier' => 'accessory', 'quantity' => -1],
                        ['identifier' => 'something_else', 'quantity' => 10000],
                    ],
                    'product_models' => [],
                    'product_drafts' => [],
                ],
            ],
        ]);
    }

    /**
     * @Then /^there is at least a validation error on this product model$/
     */
    public function thereIsAtLeastAValidationErrorOnThisProductModel()
    {
        $violations = $this->validator->validate($this->productModel);
        Assert::greaterThan(count($violations), 0);
    }
}
