<?php

class SearchUpdateImmediateProcessor extends SearchUpdateProcessor
{
    public function triggerProcessing()
    {
        $this->process();
    }
}
