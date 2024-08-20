<?php
namespace elmyrockers;

use Illuminate\Validation\PresenceVerifierInterface;
use \RedBeanPHP\R as R;

class EzAuthPresenceVerifier implements PresenceVerifierInterface {
	/**
	 * @return int
	 */
	#[\ReturnTypeWillChange]
	public function getCount( $collection, $column, $value, $excludeId = null, $idColumn = 'id', array $extra = [] )
	{
		// Build the query
		$query = R::findAll( $collection, "{$column} = ?", [$value] );

		// Exclude the specified ID if provided
		if ($excludeId !== null) {
			$query = array_filter($query, function ($item) use ($excludeId, $idColumn) {
				return $item->{$idColumn} !== $excludeId;
			});
		}

		return count($query);
	}

	/**
	 * @return int
	 */
	#[\ReturnTypeWillChange]
	public function getMultiCount( $collection, $column, array $values, $excludeId = null, $idColumn = 'id', array $extra = [] )
	{
		// Build the query
		$query = R::findAll( $collection, "{$column} IN (" . implode(',', array_fill(0, count($values), '?')) . ")", $values );

		// Exclude the specified ID if provided
		if ($excludeId !== null) {
			$query = array_filter($query, function ($item) use ($excludeId, $idColumn) {
				return $item->{$idColumn} !== $excludeId;
			});
		}

		return count($query);
	}
}