<?php

/**
 *
 * 
 * @category    TesseractOCR
 * @author      Jamie Helm
 * 
 */



namespace image;

use Imagick;

/**
 * This class handles all of the interfaces for OCR image extraction and processing.
 */
class imagetools
{

    /**
     * This method processes a specified file name, extracts the image and performs image processing to prepare it for OCR processing. The method requires Imagemagick and Ghostscript in order to function. The image is extracted from the file, inverted, processed to remove vertical lines (which are a problem for OCR) and then invert again back to normal. A lot of trial and error was used to find the best result including the following:
     * 
     * - downscaling the image to reduce line thickness and then rescaling it back. This impacted the letter quality too much
     * - using the erode morphology with an octogon kernel - this caused too much erosion on letters. Marginally better results overall.
     * - using thinning with a matrix for input - caused a significant number of artifacts which results in degraded extraction.
     * - using gausian bluring to make lines less defined - caused issues on the letters too.
     * 
     * Finally used thinning applying a rectangular kernel.
     *
     * Note that filepath needs to be defined manually. Note that extracted images are saved in to the extracted folder. This folder must be blocked from external access (CHMOD?).
     * 
     * @param  string $filename the file to be processed
     * @return bool          true or false
     */
    public function getImage($filetext)
    {
        echo("\n<br>-- Processing file ");
    //create new instance of im. Dont load file here, as cannot adjust DPI once loaded.
    $im = new Imagick();
                        
    echo("\n<br>---- Setting Paramters ");
    $im->setResolution(300,300);
    //$im->readImage($value);

    //do some basic checks. Note, we cannot check image size / height yet as it would only count first page.

    $validfile = false;
    try
    {
           //$preprocessim->readImageBlob($filetext);
           $im->readImageBlob($filetext);
           $validfile = true;
           //echo("\n<br><br>--File is valid. Process Image.");
    }
    catch (ImagickException $e) 
    {
          echo("\n<br>****Invalid File*****\n<br>");
          $resultsarray['status'] = 52;
          $resultsarray['message'] = "\n<br><br>Imagick Unable to process Blob";        
          return $resultsarray;
    }

    if($validfile == true)
    {
        //set image format
        $im->setImageFormat('jpg');
        $im->setImageType(\Imagick::IMGTYPE_GRAYSCALEMATTE); 

        echo("\n<br>---- Extracting page count: ");
        $pagecount = $im->getNumberImages();
        echo("\n<br>---- ".$pagecount." pages ");

        if($pagecount <= 7)
        {
            echo("\n<br>---- Outputting file ");
            $lastIndex = $im->getIteratorIndex();
            $im->resetIterator();

            //some PDF files ahve an issue with the transparancy on pagee 1. This resets the itterator and reprocesses the iamges.
            for($i = $im->getIteratorIndex(); $i <= $lastIndex; $i++) 
            {
                $im->setIteratorIndex($i);
                $im->setBackgroundColor('white');
                $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                
            }
            $im->resetIterator();
            $combined = $im->appendImages(true);
            $combined->setImageFormat('jpg');
            //$combined->setImageType(\Imagick::IMGTYPE_GRAYSCALEMATTE); 
            //
                
                $blob = $combined->getImagesBlob(); 
                $size = $combined->getImageLength();
                $width = $combined->getImageWidth();
                $height = $combined->getImageHeight();


                if($width > 32767 || $height > 32767)
                {
                    echo("\n<br>-***** Image too large ****");
                    $resultsarray['status'] = 52;
                    $resultsarray['message'] = "\n<br><br>Imagick Unable to process Blob";        
                    return $resultsarray;               
                }
                else
                {
                    $resultsarray['status'] = 1;
                    $resultsarray['size'] = $size;
                    $resultsarray['message'] = "Success";
                    $resultsarray['blob'] = $blob;
                    return $resultsarray;   
                    echo("\n<br>------- Image saved");
                }
        }
        else
        {
            echo("\n<br>-***** Too many pages****");
            $resultsarray['status'] = 53;
            $resultsarray['message'] = "\n<br><br>Imagick Unable to process Blob";        
            return $resultsarray;
        }
    echo("\n<br>");
        
    }
    

        
        //}

    }
    

}//close class
?>