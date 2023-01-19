<?php

/**
 * Mail Tools
 * 
 * Contains methods required for managing files received via email.
 * 
 * @category    TesseractOCR
 * @author      Jamie Helm
 * 
 */



namespace extract;

use thiagoalessio\TesseractOCR\TesseractOCR;
/**
 * This class handles all of the interfaces for OCR image extraction and processing.
 */
class extracttools
{

    /**
     * Extracts text against specified file name and configuration. Previously have a configuration 4 for inverted images, but dropped as it is not efficient.
     * @param  [type] $filename [path to file png]
     * @param  [type] $config   [config 1-3]
     * @return [type]           [extracted text]
     */
    public function getExtract($imageblobstring,$imagesize,$psm,$live)
    {
    
            $ocr  = new TesseractOCR(); 
            $live == true ? $ocr->command->threadLimit = 1 : null; 
            $ocr->imageData($imageblobstring,$imagesize);          
            $ocr = $ocr->config('preserve_interword_spaces',true);
            $ocr->psm($psm);
            $ocr->dpi(300);
            $ocr = $ocr->run();
            return $ocr;          
        
    }


}