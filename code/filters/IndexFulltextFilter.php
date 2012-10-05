<?php
/**
 * A search filter which operates against an index. Each instance must have its
 * index and fields to search configured.
 */
class IndexFulltextFilter extends SearchFilter {

	protected $index;
	protected $fields;
	protected $fuzzy;

	public function setIndex(SearchIndex $index) {
		$this->index = $index;
	}

	public function setFields($fields) {
		return $this->fields = $fields;
	}

	public function setFuzzy($fuzzy = true) {
		$this->fuzzy = $fuzzy;
	}

	public function apply(DataQuery $data) {
		$method = $this->fuzzy ? 'fuzzysearch' : 'search';

		$query = new SearchQuery();
		$query->$method($this->value, $this->fields);
		$query->inClass($this->model);
		$query->limit(1000);

		$results = $this->index->search($query);
		$ids = $results->Matches->column('ID');
		$ids = array_map('intval', $ids);

		return $data->where(sprintf(
			'"%s"."ID" IN (%s)',
			ClassInfo::baseDataClass($this->model),
			implode(', ', $ids)
		));
	}

	public function isEmpty() {
		return !$this->getValue();
	}

}
