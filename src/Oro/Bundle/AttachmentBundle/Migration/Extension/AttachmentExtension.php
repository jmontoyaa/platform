<?php

namespace Oro\Bundle\AttachmentBundle\Migration\Extension;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;

use Oro\Bundle\AttachmentBundle\EntityConfig\AttachmentScope;
use Oro\Bundle\EntityExtendBundle\Migration\OroOptions;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;

class AttachmentExtension implements ExtendExtensionAwareInterface
{
    const ATTACHMENT_TABLE_NAME             = 'oro_attachment_file';
    const ATTACHMENT_ASSOCIATION_TABLE_NAME = 'oro_attachment';

    /** @var ExtendExtension */
    protected $extendExtension;

    /** @var ExtendOptionsManager */
    protected $extendOptionsManager;

    /**
     * @param ExtendOptionsManager $extendOptionsManager
     */
    public function __construct(ExtendOptionsManager $extendOptionsManager)
    {
        $this->extendOptionsManager = $extendOptionsManager;
    }

    /**
     * {@inheritdoc}
     */
    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    /**
     * @param Schema $schema
     * @param string $sourceTable           Target entity table name
     * @param string $sourceColumnName      A column name is used to show related entity
     * @param string $type                  file OR image
     * @param array  $options               Additional options for relation
     * @param int    $attachmentMaxSize     Max allowed file size in MB
     * @param int    $attachmentThumbWidth  Thumbnail width in PX (used in viewAction)
     * @param int    $attachmentThumbHeight Thumbnail height in PX (used in viewAction)
     */
    public function addFileRelation(
        Schema $schema,
        $sourceTable,
        $sourceColumnName,
        $type,
        $options = [],
        $attachmentMaxSize = 1,
        $attachmentThumbWidth = 32,
        $attachmentThumbHeight = 32
    ) {
        $entityTable = $schema->getTable($sourceTable);

        $attachmentScopeOptions = [
            'maxsize' => $attachmentMaxSize
        ];

        if ($type == AttachmentScope::ATTACHMENT_IMAGE) {
            $attachmentScopeOptions['width']  = $attachmentThumbWidth;
            $attachmentScopeOptions['height'] = $attachmentThumbHeight;
        }

        $relationOptions = [
            'extend'     => [
                'owner'     => ExtendScope::OWNER_SYSTEM,
                'is_extend' => true
            ],
            'attachment' => $attachmentScopeOptions
        ];

        if (!empty($options)) {
            $relationOptions = array_merge($relationOptions, $options);
        }

        $this->extendExtension->addManyToOneRelation(
            $schema,
            $entityTable,
            $sourceColumnName,
            self::ATTACHMENT_TABLE_NAME,
            'id',
            $relationOptions
        );

        $this->extendOptionsManager->setColumnType($sourceTable, $sourceColumnName, $type);
    }

    /**
     * Adds the association between the target table and the attachment table
     *
     * @param Schema $schema
     * @param string $targetTableName Target entity table name
     * @param array  $allowedMimeTypes
     * @param int    $maxsize
     */
    public function addAttachmentAssociation(
        Schema $schema,
        $targetTableName,
        array $allowedMimeTypes = [],
        $maxsize = 1
    ) {
        $noteTable   = $schema->getTable(self::ATTACHMENT_ASSOCIATION_TABLE_NAME);
        $targetTable = $schema->getTable($targetTableName);

        $primaryKeyColumns = $targetTable->getPrimaryKeyColumns();
        $targetColumnName  = array_shift($primaryKeyColumns);

        $options = new OroOptions();
        $options->set('attachment', 'enabled', true);
        $options->set('attachment', 'maxsize', $maxsize);
        $options->set('attachment', 'mimetypes', implode("\n", $allowedMimeTypes));
        $targetTable->addOption(OroOptions::KEY, $options);

        $associationName = ExtendHelper::buildAssociationName(
            $this->extendExtension->getEntityClassByTableName($targetTableName)
        );

        $this->extendExtension->addManyToOneRelation(
            $schema,
            $noteTable,
            $associationName,
            $targetTable,
            $targetColumnName,
            [
                'extend' => [
                    'owner'     => ExtendScope::OWNER_SYSTEM,
                    'is_extend' => true
                ]
            ]
        );
    }
}
