<?php




?>


  <head>
    <!-- Required meta tags -->
    

    <title>Document Scanning Portal</title>
    <?php 

        if($metarefresh == true)
        {
            echo('<meta http-equiv="refresh" content="'.$metatime.'">');       
        }        

    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" type="image/png" href="../images/favicon.ico">

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>


    <!-- datepicker -->  
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">


    <!-- text editor --> 
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>  

    <!-- Charting  --> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



<script>

//Ajax form poster for invoice processing.
$(document).ready(function(){

 if($('#password1').length > 0)
    {
    //for view password
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password1');
    const password2 = document.querySelector('#password2');
 
    togglePassword.addEventListener('click', function (e) {
    // toggle the type attribute
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);

    const type2 = password2.getAttribute('type') === 'password' ? 'text' : 'password';
    password2.setAttribute('type', type2);    

    // toggle the eye slash icon
    this.classList.toggle('fa-eye-slash');

    });
    }

 if($('#password').length > 0)
    {
    //for view password
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

 
    togglePassword.addEventListener('click', function (e) {
    // toggle the type attribute
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);

    // toggle the eye slash icon
    this.classList.toggle('fa-eye-slash');

    });
    }


    var timer = null;
    $('#searchtext').keydown(function()
    {
       clearTimeout(timer); 
       timer = setTimeout(searchHandoff, 2000)
    });

    function searchHandoff() {
        //alert('Ready to search');
        str = $('#searchtext').val();
        showResult(str);
    }

});
</script>

<script>

//predictive search once timer limit reached
function showResult(str) 
    {


    if (str.length==0) 
    {
      document.getElementById("search").innerHTML="";
      document.getElementById("search").style.border="0px";
      return;
    }
    var xmlhttp=new XMLHttpRequest();
    xmlhttp.onreadystatechange=function()
      {
      if (this.readyState==4 && this.status==200) {
        document.getElementById("search").innerHTML=this.responseText;
        document.getElementById("search").style.border="1px solid #336699";
      }
    }
    xmlhttp.open("GET","search.php?q="+str,true);
    xmlhttp.send();
  }
         
              
</script>




<style>
.pagenumbers 
{
    border-collapse: separate;
    border-spacing: 0.5em;
}

.strokeme
{
    color: white;
    text-shadow:
    -1px -1px 0 #000,
    1px -1px 0 #000,
    -1px 1px 0 #000,
    1px 1px 0 #000;  
}

.strokeme2
{
    color: black;
    text-shadow:
    -1px -1px 0 #FFF,
    1px -1px 0 #FFF,
    -1px 1px 0 #FFF,
    1px 1px 0 #FFF;  
}




* {box-sizing: border-box;}

.img-zoom-container {
  position: relative;
}

.img-zoom-lens {
  position: absolute;
  border: 1px solid #d4d4d4;
  /*set the size of the lens:*/
  width: 40px;
  height: 40px;
}

.img-zoom-result {
  border: 1px solid #d4d4d4;
  /*set the size of the result div:*/
  width: 300px;
  height: 300px;
}


</style>



</head>
