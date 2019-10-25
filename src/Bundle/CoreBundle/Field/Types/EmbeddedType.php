<?php


namespace UniteCMS\CoreBundle\Field\Types;

use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UniteCMS\CoreBundle\Content\ContentInterface;
use UniteCMS\CoreBundle\Content\Embedded\EmbeddedContent;
use UniteCMS\CoreBundle\Content\Embedded\EmbeddedFieldData;
use UniteCMS\CoreBundle\Content\FieldData;
use UniteCMS\CoreBundle\Content\FieldDataMapper;
use UniteCMS\CoreBundle\ContentType\ContentType;
use UniteCMS\CoreBundle\ContentType\ContentTypeField;
use UniteCMS\CoreBundle\Domain\DomainManager;

class EmbeddedType extends AbstractFieldType
{
    const TYPE = 'embedded';

    /**
     * @var DomainManager
     */
    protected $domainManager;

    /**
     * @var FieldDataMapper $fieldDataMapper
     */
    protected $fieldDataMapper;

    /**
     * @var LoggerInterface $uniteCMSDomainLogger
     */
    protected $domainLogger;

    public function __construct(DomainManager $domainManager, FieldDataMapper $fieldDataMapper, LoggerInterface $uniteCMSDomainLogger)
    {
        $this->domainManager = $domainManager;
        $this->fieldDataMapper = $fieldDataMapper;
        $this->domainLogger = $uniteCMSDomainLogger;
    }

    /**
     * {@inheritDoc}
     */
    public function validateFieldDefinition(ContentType $contentType, ContentTypeField $field, ExecutionContextInterface $context) : void {

        // Validate return type.
        $returnTypes = empty($field->getUnionTypes()) ? [$field->getReturnType()] : array_keys($field->getUnionTypes());
        foreach($returnTypes as $returnType) {
            if(!$this->domainManager->current()->getContentTypeManager()->getEmbeddedContentType($returnType)) {
                $context
                    ->buildViolation('Invalid GraphQL return type "{{ return_type }}" for field of type "{{ type }}". Please use a GraphQL type (or an union of types) that implements UniteEmbeddedContent.')
                    ->setParameter('{{ type }}', static::getType())
                    ->setParameter('{{ return_type }}', $field->getReturnType())
                    ->addViolation();
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function GraphQLInputType(ContentTypeField $field) : string {
        return sprintf('%sInput', $field->getReturnType());
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveRowData(ContentInterface $content, ContentTypeField $field, FieldData $fieldData) {
        return new EmbeddedContent($fieldData->getId(), $fieldData->getType(), $fieldData->getData());
    }

    /**
     * {@inheritDoc}
     */
    public function normalizeInputData(ContentInterface $content, ContentTypeField $field, $inputData = null) : FieldData {

        $domain = $this->domainManager->current();

        // If this is not a known embedded type.
        if(!$contentType = $domain->getContentTypeManager()->getEmbeddedContentType($field->getReturnType())) {
            $this->domainLogger->warning(sprintf('Unknown embedded content type "%s" was used as return type of field "%s".', $field->getReturnType(), $field->getId()));
            return null;
        }

        // Create new embedded content with input data.
        return new EmbeddedFieldData(
            uniqid(),
            $contentType->getId(),
            $this->fieldDataMapper->mapToFieldData($domain, $content, $inputData, $contentType)
        );
    }
}
