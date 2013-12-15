<?php

namespace Ecommerce\Bundle\ElasticsearchBundle\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Elastica\Type;
use Elastica\Document;
use FOS\ElasticaBundle\Provider\ProviderInterface;

use Ecommerce\Bundle\CatalogBundle\Product\ProductManager;
use Ecommerce\Bundle\CatalogBundle\Doctrine\Phpcr\Product;
use Ecommerce\Bundle\CatalogBundle\Doctrine\Phpcr\ProductInterface;
use Ecommerce\Bundle\CatalogBundle\Event\ProductEvent;


class ProductProvider implements ProviderInterface
{
    /** @var Type */
    protected $productType;

    /** @var ProductManager */
    private $productManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Constructor.
     */
    public function __construct(Type $productType, ProductManager $productManager)
    {
        $this->productType        = $productType;
        $this->productManager     = $productManager;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
    }


    /**
     * @see \FOS\ElasticaBundle\Provider\ProviderInterface::populate()
     */
    public function populate(\Closure $loggerClosure = null, array $options = array())
    {
        if ($loggerClosure) {
            $loggerClosure('Indexing products');
        }

        $products = $this->productManager->findAll();

        if (empty($products)) {
            return;
        }

        $documents = array();

        foreach ($products as $product) {
            $document = $this->transformToDocument($product);

            if (!$document || !$this->isValid($document)) {
                continue;
            }

            $documents[] = $document;
        }

        if (empty($documents)) {
            return;
        }

        $this->productType->addDocuments($documents);
    }

    public function insertDocument(Product $product)
    {
        $document = $this->transformToDocument($product);
        $this->productType->addDocument($document);
    }

    public function updateDocument(Product $product)
    {
        $document = $this->transformToDocument($product);
        $this->productType->updateDocument($document);
    }




    public function transformToDocument(Product $product)
    {
        $data = array(
            'id'   => $product->getIdentifier(),
            'name' => $product->getName(),
        );

        $ignoredProperties = array(
            'jcr:primaryType',
            'jcr:mixinTypes',
            'phpcr:class',
            'phpcr:classparents',
            'jcr:uuid',
        );
//        $productData = array_diff_key($product->getPublicNodeProperties(), array_flip($ignoredProperties));

        $productData = $product->getTranslatedProperties();

        $data = array_merge($data, $productData);



        // @todo Dispatch additional event

        $data = $this->filterValues($data);


        $data['last_synced_at'] = date('c');

        $document = new Document($product->getIdentifier(), $data);

        return $document;
    }

    private function filterValues($data)
    {
        $renamedProperties = array(
            'created_by' => 'jcr:createdBy',
            'created_at' => 'jcr:created',
            'updated_at' => 'jcr:lastModifiedBy',
            'updated_by' => 'jcr:lastModified',
        );

        $filteredData = array();
        foreach ($data as $property => $value) {

            if (is_array($value) && !empty($value) && array_keys($value) !== range(0, count($value) - 1)) {

                if ($throwLocaleAway = false) {
                    $value = array_values($value);
                } elseif ($mappingForTranslatedValues = true) {
                    $nestedArray = array();
                    foreach ($value as $locale => $translation) {
                        $nestedArray[] = array(
                            'locale' => $locale,
                            'value' => $translation,
                        );
                    }
                    $value = $nestedArray;
                } else {
                    $nestedArray = array();
                    foreach ($value as $locale => $translation) {
                        $nestedArray[] = array(
                            $locale,
                            $translation,
                        );
                    }
                    $value = $nestedArray;
                }
            }

            if ($value instanceof \DateTime) {
                $value = $value->format('c');
            }

            if (($newPropertyKey = array_search($property, $renamedProperties)) !== false) {
                $property = $newPropertyKey;
            }

            $filteredData[$property] = $value;
        }

        return $filteredData;
    }



    private function isValid(Document $document)
    {
        if ($document->has('inactive') && $document->has('inactive')) {
            return false;
        }

        return true;
    }

    public function postUpdate(ProductEvent $event)
    {
        $product = $event->getProduct();
        if ($product instanceof ProductInterface) {
            $this->insertDocument($product);
        }
    }

}
