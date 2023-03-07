<?php

namespace BX\Data\Provider;

use ArrayObject;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\ORM\ElementEntity;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Objectify\EntityObject;
use Bitrix\Main\ORM\Objectify\State;
use Bitrix\Main\SystemException;
use CIBlockElement;
use Data\Provider\Interfaces\OperationResultInterface;
use Data\Provider\Interfaces\PkOperationResultInterface;
use Data\Provider\Interfaces\QueryCriteriaInterface;
use Data\Provider\OperationResult;
use Exception;

class IblockDataProvider extends DataManagerDataProvider implements IblockDataProviderInterface
{
    /**
     * @var ElementEntity
     */
    private $elementEntity;
    /**
     * @var EntityObject|null
     */
    private $iblock;

    /**
     * @param string $iblockType
     * @param string $iblockCode
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException|LoaderException
     * @throws Exception
     */
    public function __construct(string $iblockType, string $iblockCode)
    {
        Loader::includeModule('iblock');
        $this->iblock = IblockTable::getList([
            'filter' => [
                '=IBLOCK_TYPE_ID' => $iblockType,
                '=CODE' => $iblockCode,
            ],
            'limit' => 1,
        ])->fetchObject();

        if (empty($this->iblock)) {
            throw new Exception('iblock is not found');
        }

        $this->elementEntity = IblockTable::compileEntity($this->iblock);
        parent::__construct($this->elementEntity->getDataClass());
    }

    /**
     * @param mixed $data
     * @param int|null $pk
     * @return EntityObject
     * @throws ArgumentException
     * @throws SystemException
     */
    private function initItem($data, int $pk = null): EntityObject
    {
        $currentUserId = $this->getCurrentUserId();
        $createdByIsEmpty = array_key_exists('CREATED_BY', $data) === false;
        if ($createdByIsEmpty && $currentUserId > 0) {
            $data['CREATED_BY'] = $currentUserId;
        }

        $modifiedByIsEmpty = array_key_exists('MODIFIED_BY', $data) === false;
        if ($modifiedByIsEmpty && $currentUserId > 0) {
            $data['MODIFIED_BY'] = $currentUserId;
        }

        $item = $this->elementEntity->createObject();
        if ((int)$pk > 0) {
            $item->setId($pk);
            $item->sysChangeState(State::CHANGED);
        }

        $search = '_VALUE';
        foreach ($data as $key => $value) {
            if (strpos($key, $search)) {
                $key = str_replace($search, '', $key);
            }

            if ($key !== 'ID' && !is_null($value) && !is_array($value)) {
                $item->set($key, $value);
            }
        }

        return $item;
    }

    /**
     * @param EntityObject $item
     * @param array|ArrayObject $data
     * @return void
     * @throws ArgumentException
     * @throws SystemException
     */
    private function updateMultiValueProps(EntityObject $item, $data)
    {
        $search = '_VALUE';
        foreach ($data as $key => $value) {
            if (strpos($key, $search)) {
                $key = str_replace($search, '', $key);
            }

            if ($key !== 'ID' && is_array($value)) {
                /**
                 * @psalm-suppress UndefinedClass
                 */
                CIBlockElement::SetPropertyValues(
                    $item->getId(),
                    $this->iblock->getId(),
                    $value,
                    $key
                );
            }
        }
    }

    /**
     * @return int
     * @throws SystemException
     */
    public function getIblockId(): int
    {
        if (!($this->iblock instanceof EntityObject)) {
            return 0;
        }

        return (int)$this->iblock->getId();
    }

    /**
     * @return string
     * @throws SystemException
     */
    public function getIblockCode(): string
    {
        if (!($this->iblock instanceof EntityObject)) {
            return '';
        }

        return (string)$this->iblock->get('CODE');
    }

    public function getIblockType(): string
    {
        if (!($this->iblock instanceof EntityObject)) {
            return '';
        }

        return (string)$this->iblock->get('IBLOCK_TYPE_ID');
    }

    /**
     * @param array|ArrayObject $data
     * @param QueryCriteriaInterface|null $query
     * @return PkOperationResultInterface
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function saveInternal(&$data, QueryCriteriaInterface $query = null): PkOperationResultInterface
    {
        if (empty($query)) {
            $dataResult = ['data' => $data];
            $item = $this->initItem($data);
            $addResult = $item->save();
            if ($addResult->isSuccess()) {
                $this->updateMultiValueProps($item, $data);
                $pkValue = $addResult->getId();
                $data[$this->getPkName() ?? 'ID'] = $pkValue;
                $data['IBLOCK_ID'] = $this->getIblockId();

                return new OperationResult(null, $dataResult, $pkValue);
            }

            return new OperationResult(
                implode(', ', $addResult->getErrorMessages()),
                $dataResult
            );
        }

        $dataResult = ['query' => $query, 'data' => $data];
        $errorMessage = 'Данные для обновления не найдены';
        $pkName = $this->getPkName();
        if (empty($pkName)) {
            return new OperationResult(
                $errorMessage,
                $dataResult
            );
        }

        $bxQuery = BxQueryAdapter::init($query);
        $pkListForUpdate = $this->getPkValuesByQuery($bxQuery);

        if (empty($pkListForUpdate)) {
            return new OperationResult(
                $errorMessage,
                $dataResult
            );
        }

        $mainResult = null;
        foreach ($pkListForUpdate as $pkValue) {
            $item = $this->initItem($data, (int)$pkValue);
            $bxResult = $item->save();
            $saveResult = $bxResult->isSuccess() ?
                new OperationResult('', $dataResult, $pkValue) :
                new OperationResult(implode(', ', $bxResult->getErrorMessages()), $dataResult, $pkValue);

            if ($mainResult instanceof OperationResultInterface) {
                $mainResult->addNext($saveResult);
            } else {
                $mainResult = $saveResult;
            }
        }

        $data['IBLOCK_ID'] = $this->getIblockId();

        return $mainResult instanceof PkOperationResultInterface ?
            $mainResult :
            new OperationResult('Данные для сохранения не найдены', $dataResult);
    }

    private function getCurrentUserId(): int
    {
        return (int) CurrentUser::get()->getId();
    }
}
