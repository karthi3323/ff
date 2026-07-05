<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "config/constants.php";

?>
<!-- <!DOCTYPE html> -->
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Billing System</title>
        <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/css2.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <style>

       /*  @font-face {
            font-family: Montserrat-Regular;
            src: url('../fonts/montserrat/Montserrat-Regular.ttf');
        }

        @font-face {
            font-family: Montserrat-Medium;
            src: url('../fonts/montserrat/Montserrat-Medium.ttf');
        }

        @font-face {
            font-family: Montserrat-Bold;
            src: url('../fonts/montserrat/Montserrat-Bold.ttf');
        }

        @font-face {
            font-family: Montserrat-Italic;
            src: url('../fonts/montserrat/Montserrat-Italic.ttf');
        }

        @font-face {
            font-family: Montserrat-Black;
            src: url('../fonts/montserrat/Montserrat-Black.ttf');
        }

        @font-face {
            font-family: Linearicons;
            src: url('../fonts/Linearicons-Free-v1.0.0/WebFont/Linearicons-Free.ttf');
        }

        @font-face {
            font-family: Poppins-Bold;
            src: url('../fonts/poppins/Poppins-Bold.ttf');
        }

        @font-face {
            font-family: Poppins-Black;
            src: url('../fonts/poppins/Poppins-Black.ttf');
        } */

        /*[ RESTYLE TAG ]
            ///////////////////////////////////////////////////////
        */
        * {
            margin: 0px;
            padding: 0px;
            box-sizing: border-box;
        }

        body,
        table,
        html {

            height: 100%;

            font-family: Montserrat-Regular, sans-serif;
            font-weight: 400;
        }

        /* ------------------------------------ */
        a {
            font-family: Montserrat-Regular;
            font-weight: 400;
            font-size: 15px;
            line-height: 1.7;
            color: #666666;
            margin: 0px;
            transition: all 0.4s;
            -webkit-transition: all 0.4s;
            -o-transition: all 0.4s;
            -moz-transition: all 0.4s;
        }

        a:focus {
            outline: none !important;
        }

        a:hover {
            text-decoration: none;
            color: none;
        }

        .sm-text-over {
            color: #0056b3 !important;
            font-weight: bold;
            font-size: 18px;
        }

        .sm-text-over1 {
            color: #fff700 !important;
            font-weight: bold;
            font-size: 18px;
        }

        /* ------------------------------------ */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            margin: 0px;
        }

        p {
            font-family: Montserrat-Regular;
            font-size: 15px;
            line-height: 1.7;
            color: #888888;
            margin: 0px;
        }

        ul,
        li {
            margin: 0px;
            list-style-type: none;
        }


        /* ------------------------------------ */
        input {
            outline: none;
            border: none !important;
        }

        textarea {
            outline: none;
        }

        /* textarea:focus, input:focus {
        border-color: transparent !important;
        } */

        input:focus::-webkit-input-placeholder {
            color: transparent;
        }

        input:focus:-moz-placeholder {
            color: transparent;
        }

        input:focus::-moz-placeholder {
            color: transparent;
        }

        input:focus:-ms-input-placeholder {
            color: transparent;
        }

        textarea:focus::-webkit-input-placeholder {
            color: transparent;
        }

        textarea:focus:-moz-placeholder {
            color: transparent;
        }

        textarea:focus::-moz-placeholder {
            color: transparent;
        }

        textarea:focus:-ms-input-placeholder {
            color: transparent;
        }

        /* ------------------------------------ */
        button {
            outline: none !important;
            border: none;
            background: transparent;
        }

        button:hover {
            cursor: pointer;
        }

        iframe {
            border: none !important;
        }


        /* ------------------------------------ */
        .container {
            max-width: 90%;
        }

        .footer_copy {
            padding-left: 0 !important;
            padding-right: 0 !important;
            background-color: #636363 !important;
            color: #fff !important;
        }

        .page_wrapper {
            min-height: 100%;
        }

        .piclist {
            margin-top: 30px;
        }

        .piclist li {
            display: inline-block;
            width: 50px;
            height: 50px;
        }

        .piclist li img {
            width: 100%;
            height: auto;
        }

        /* custom style */
        .picZoomer-pic-wp,
        .picZoomer-zoom-wp {
            border: 1px solid #fff;
        }

        #exzoom {
            width: 100%;
        }

        .exzoom_img_box .exzoom_img_ul_outer {
            width: 100% !important;
            height: 100% !important;
        }

        .exzoom .exzoom_img_box {
            background: #eee !important;
            position: relative !important;
            background-image: url('<?= ASSETS_URL . '/img/trademark.png'?>') !important;
            background-repeat: no-repeat;
        }

        .padding-left {
            padding-left: 2% !important;
        }

        .des_text {
            font-size: 14px;
            color: #000;
        }


        .success {
            color: green;
            font-weight: bold;
            padding-bottom: 10px;
            animation-timing-function: 1000;

        }

        .error {
            color: red;
            font-weight: bold;
            padding-bottom: 10px;
        }

        .p-3 {
            padding: 0.25rem !important;
        }

        @media only screen and (min-width: 1200px) {
            .carousel-caption {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 42% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                transform: translate(-50%, -50%);

            }

            .categoreies {
                position: relative !important;
                top: 0em !important;
                /*margin: 0 auto; */
                left: 0em !important;
            }

            .carousel-caption p {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: -10% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 100% !important;
                margin: 0 !important;
            }

            .carousel-caption label {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 45% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 140% !important;
                margin: 0 !important;
            }

            .home_slider {
                padding-top: 125px !important;
            }

            .banner_img {
                position: relative !important;
                top: 0em !important;
            }
        }

        @media (max-width:992px) and (min-width:768px) {
            .carousel-caption {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 38% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;

            }

            .categoreies {
                position: relative !important;
                top: 0em !important;
                /*margin: 0 auto; */
                left: 0em !important;
            }

            .carousel-caption h1 img {
                width: 200px;
            }

            .carousel-caption p img {
                width: 250px;
            }

            .carousel-caption label img {
                width: 150px;
            }

            .carousel-caption p {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 0% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 60% !important;
                margin: 0 !important;
            }

            .carousel-caption label {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 35% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 100% !important;
                margin: 0 !important;
            }

            .s-text12 {
                font-size: 10px !important;
            }

            .s-text8px {
                font-size: 8px !important;
                font-weight: bold;
            }

            .banner_img {
                position: relative !important;
                top: 0em !important;
            }

            .home_slider {
                padding-top: 110px !important;
            }
        }


        @media (max-width:768px) and (min-width:400px) {
            .carousel-caption {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 38% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;

            }

            .categoreies_list {
                display: none !important;
            }

            .categoreies {
                position: relative !important;
                top: 0em !important;
                /*margin: 0 auto; */
                left: 0em !important;
            }

            .carousel-caption h1 img {
                width: 200px;
            }

            .carousel-caption p img {
                width: 250px;
            }

            .carousel-caption label img {
                width: 150px;
            }

            .carousel-caption p {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 0% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 60% !important;
                margin: 0 !important;
            }

            .carousel-caption label {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 35% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 100% !important;
                margin: 0 !important;
            }

            .banner_img {
                position: relative !important;
                top: 0em !important;
            }

            .home_slider {
                padding-top: 110px !important;
            }
        }

        @media (max-width:1024px) and (min-width:375px) {

            .carousel-caption {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 38% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;

            }

            .categoreies {
                position: relative !important;
                top: 0em !important;
                /*margin: 0 auto; */
                left: 0em !important;
            }

            .carousel-caption h1 img {
                width: 200px;
            }

            .carousel-caption p img {
                width: 250px;
            }

            .carousel-caption label img {
                width: 150px;
            }

            .carousel-caption p {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 0% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 60% !important;
                margin: 0 !important;
            }

            .carousel-caption label {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 35% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 100% !important;
                margin: 0 !important;
            }

            .carousel-caption h1 img {
                width: 150px;
            }

            .carousel-caption p img {
                width: 200px;
            }

            .carousel-caption label img {
                width: 100px;
            }

            .carousel-caption {
                top: 0%;
            }

        }

        @media (max-width:812px) and (min-width:375px) {

            .carousel-caption {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 38% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;

            }

            carousel-caption p {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 0% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 60% !important;
                margin: 0 !important;
            }

            .carousel-caption label {
                position: absolute !important;
                right: 50% !important;
                bottom: 50% !important;
                left: 35% !important;
                z-index: 10 !important;
                padding-top: 20px !important;
                padding-bottom: 20px !important;
                color: #fff !important;
                text-align: center !important;
                top: 100% !important;
                margin: 0 !important;
            }

            .carousel-caption {
                top: 20% !important;
            }

        }

        @media (max-width:1025px) and (min-width:1024px) {
            .carousel-caption h1 img {
                width: 350px !important;
            }

            .carousel-caption p img {
                width: 400px !important;
            }

            .carousel-caption label img {
                width: 300px !important;
            }

            .carousel-caption {
                top: 22% !important;
            }
        }

        div.ri {

            padding: 1em;
            position: absolute;
            max-width: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            align-items: center;

        }

        div.ri img {
            padding: 1em;
        }


        .product_banner {
            background-image: url(../images/products_old1.png);
            width: 100%;
            min-height: 239px;
            background-repeat: no-repeat;
            background-size: 100% 100%;
        }

        @media (max-width:896px) and (min-width:320px) {
            div.ri {

                padding: 1em !important;
                position: absolute !important;
                max-width: 40% !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                text-align: center !important;
                align-items: center !important;


            }

            .about_banner {
                background-image: url(../images/heading-pages-06.jpg);
                width: 100%;
                height: 100%;
                min-height: 239px;
                max-height: 400px;
                background-repeat: no-repeat;
                background-size: 100% 239px;
                display: none !important;
            }

            .mobile_about_banner {
                background-image: url(../images/mobile-banner.png);
                width: 100%;
                height: 100%;
                min-height: 239px;
                max-height: 400px;
                background-repeat: no-repeat;
                background-size: 100% 239px;
                display: block !important;
            }

            div.ri img {
                padding: 0em !important;
            }
        }


        @media (max-width:699px) {
            div.ri {

                padding: 1em !important;
                position: absolute !important;
                max-width: 50% !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                text-align: center !important;
                align-items: center !important;


            }

            div.ri img {
                padding: 0em !important;
            }
        }




        @media screen and (orientation: portrait) {
            img.ri {
                max-width: 90%;
            }
        }

        @media screen and (orientation: landscape) {
            img.ri {
                max-height: 90%;
            }
        }

        .about_banner {
            background-image: url(../images/heading-pages-06.jpg);
            width: 100%;
            height: 100%;
            min-height: 239px;
            max-height: 400px;
            background-repeat: no-repeat;
            background-size: 100% 239px;
            display: block;
        }

        .home_slider {
            padding-top: 110px;
        }

        .slick-slide {
            outline: none !important;
        }

        .det-t-c {
            color: #000;
        }

        #padding_right {
            padding-right: 10px;
        }

        .padding {
            padding: 10px;
        }

        .none_padding {
            padding-right: 0px !important;
            padding-left: 0px !important;
            margin: 0px !important;
        }

        .main_content {
            min-height: 100%;
        }

        .footer_b {
            background-color: #868686 !important;
            color: #fff !important;
        }

        /*[ LOADDING ]
            ///////////////////////////////////////////////////////////
            */
        .animsition-loading-1 {
            position: absolute;
            top: 50%;
            left: 50%;
            -webkit-transform: translate(-50%, -50%);
            -moz-transform: translate(-50%, -50%);
            -ms-transform: translate(-50%, -50%);
            -o-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
        }

        [data-loader='ball-scale'] {
            width: 50px;
            height: 50px;
            -webkit-animation: ball-scale infinite linear .75s;
            -moz-animation: ball-scale infinite linear .75s;
            -o-animation: ball-scale infinite linear .75s;
            animation: ball-scale infinite linear .75s;
            border-radius: 100%;
            background-color: #e65540;
        }

        @-webkit-keyframes ball-scale {
            0% {
                -webkit-transform: scale(.1);
                -ms-transform: scale(.1);
                -o-transform: scale(.1);
                transform: scale(.1);
                opacity: 1;
            }

            100% {
                -webkit-transform: scale(1);
                -ms-transform: scale(1);
                -o-transform: scale(1);
                transform: scale(1);
                opacity: 0;
            }
        }

        @-moz-keyframes ball-scale {
            0% {
                -webkit-transform: scale(.1);
                -ms-transform: scale(.1);
                -o-transform: scale(.1);
                transform: scale(.1);
                opacity: 1;
            }

            100% {
                -webkit-transform: scale(1);
                -ms-transform: scale(1);
                -o-transform: scale(1);
                transform: scale(1);
                opacity: 0;
            }
        }

        @-o-keyframes ball-scale {
            0% {
                -webkit-transform: scale(.1);
                -ms-transform: scale(.1);
                -o-transform: scale(.1);
                transform: scale(.1);
                opacity: 1;
            }

            100% {
                -webkit-transform: scale(1);
                -ms-transform: scale(1);
                -o-transform: scale(1);
                transform: scale(1);
                opacity: 0;
            }
        }

        @keyframes ball-scale {
            0% {
                -webkit-transform: scale(.1);
                -ms-transform: scale(.1);
                -o-transform: scale(.1);
                transform: scale(.1);
                opacity: 1;
            }

            100% {
                -webkit-transform: scale(1);
                -ms-transform: scale(1);
                -o-transform: scale(1);
                transform: scale(1);
                opacity: 0;
            }
        }

        /*[ BACK TO TOP ]
            ///////////////////////////////////////////////////////////
            */
        .btn-back-to-top {
            display: none;
            position: fixed;
            width: 40px;
            height: 40px;
            bottom: 100px;
            right: 40px;
            background-color: black;
            opacity: 0.5;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            border-radius: 4px;
            transition: all 0.4s;
            -webkit-transition: all 0.4s;
            -o-transition: all 0.4s;
            -moz-transition: all 0.4s;
        }

        .symbol-btn-back-to-top {
            font-size: 22px;
            color: white;
            line-height: 1em;
        }

        .btn-back-to-top:hover {
            opacity: 1;
            cursor: pointer;
        }

        @media (max-width: 576px) {
            .btn-back-to-top {
                bottom: 15px;
                right: 15px;
            }
        }

        /*[ Restyle Select2 ]
            ///////////////////////////////////////////////////////////
            */
        /* Select2 */
        .select2-container {
            display: block;
            max-width: 100% !important;
            width: auto !important;
        }

        .select2-container .select2-selection--single {
            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            align-items: center;
            background-color: transparent;
            border: none;
            height: 20px;
            outline: none;
            position: relative;
        }

        /* in select */
        .select2-container .select2-selection--single .select2-selection__rendered {
            font-size: 13px;
            font-family: Montserrat-Regular;
            line-height: 20px;
            color: #888888;
            padding-left: 0px;
            background-color: transparent;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 20px;
            top: 50%;
            transform: translateY(-50%);
            right: 0px;
            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .select2-selection__arrow b {
            display: none;
        }

        .select2-selection__arrow:after {
            content: '';
            display: block;
            width: 5px;
            height: 5px;
            background-color: transparent;
            border-right: 1px solid #888888;
            border-bottom: 1px solid #888888;
            color: white;
            -webkit-transform: rotate(45deg);
            -moz-transform: rotate(45deg);
            -ms-transform: rotate(45deg);
            transform: rotate(45deg);
            margin-bottom: 2px;
            margin-right: 8px;
        }

        /* dropdown option */
        .select2-container--open .select2-dropdown {
            z-index: 1251;
            border: 1px solid #e5e5e5;
            border-radius: 0px;
            background-color: white;
        }

        .select2-container .select2-results__option[aria-selected] {
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .select2-container .select2-results__option[aria-selected="true"] {
            background-color: #e65540;
            color: white;
        }

        .select2-container .select2-results__option--highlighted[aria-selected] {
            background-color: #e65540;
            color: white;
        }

        .select2-results__options {
            font-size: 13px;
            font-family: Montserrat-Regular;
            color: #888888;
        }

        .select2-search--dropdown .select2-search__field {
            border: 1px solid #aaa;
            outline: none;
            font-family: Montserrat-Regular;
            font-size: 13px;
            color: #888888;
        }

        /*[ rs1-select2 ]
            -----------------------------------------------------------
            */
        .rs1-select2 .select2-container {
            margin-left: 26px;
        }

        .rs1-select2 .select2-container .select2-selection--single {
            height: 20px;
            ;
        }

        /*[ rs2-select2 ]
        -----------------------------------------------------------
        */
        .rs2-select2 .select2-container .select2-selection--single {
            background-color: white;
            height: 50px;
        }

        .rs2-select2 .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 20px;
            color: #555555;
            padding-left: 22px;
        }

        .rs2-select2 .select2-container--default .select2-selection--single .select2-selection__arrow {
            right: 10px;
        }

        #dropDownSelect2 .select2-results__options {
            color: #555555;
        }

        #dropDownSelect2 .select2-search--dropdown .select2-search__field {
            color: #555555;
        }



        /*[ rs3-select2 ]
        -----------------------------------------------------------
        */
        .rs3-select2 .select2-container .select2-selection--single {
            height: 45px;
        }

        .rs3-select2 .select2-selection__arrow b {
            display: block;
        }

        .rs3-select2 .select2-selection__arrow:after {
            display: none;
        }

        /*[ rs4-select2 ]
        -----------------------------------------------------------
        */
        .rs4-select2 .select2-container .select2-selection--single {
            height: 40px;
        }

        .rs4-select2 .select2-container .select2-selection--single .select2-selection__rendered {
            padding-left: 15px;
        }

        .rs4-select2 .select2-container--default .select2-selection--single .select2-selection__arrow {
            right: 5px;
        }


        /*[ Header ]
        ///////////////////////////////////////////////////////////
        */


        .header1 {
            height: 65px;
            -webkit-transition: all 0.3s;
            -o-transition: all 0.3s;
            -moz-transition: all 0.3s;
            transition: all 0.3s;
        }

        .banner_img {
            background-color: #f5f5f;
            position: relative;
            top: 0em;
        }

        .fixed-header {
            height: 110px;
        }


        /*[ Header Desktop ]
        >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/

        .container-menu-header {
            width: 100%;
            top: 0;
            left: 0;
            position: fixed;
            z-index: 1100;
            box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
            -moz-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
            -webkit-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
            -o-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
            -ms-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
        }

        /*[ Top bar ]
        ===========================================================*/
        .topbar {
            height: 45px;
            background-color: #f5f5f5;
            position: relative;
            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            justify-content: center;
            align-items: center;
        }


        /* ------------------------------------ */

        .topbar-social {
            position: absolute;
            height: 100%;
            top: 0;
            left: 0;
            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            align-items: center;
            padding-left: 40px;
        }

        .topbar-social-item {
            font-size: 18px;
            color: #888888;
            padding: 10px;
        }

        .float {
            float: left !important;
            padding-left: 0px !important;
            padding-right: 0px !important
        }

        /* ------------------------------------ */
        .topbar-email,
        .topbar-child1 {
            font-family: Montserrat-Regular;
            font-size: 13px;
            color: #888888;
            line-height: 1.7;
        }

        /* ------------------------------------ */
        .topbar-child2 {
            position: absolute;
            height: 100%;
            top: 0;
            right: 0;
            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            padding-right: 38px;
        }


        /*[ Menu ]
        ===========================================================*/
        .wrap_header {
            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            flex-wrap: wrap;
            width: 100%;
            height: auto;
            background-color: white;
            justify-content: center;
            align-items: center;
            position: relative;
            -webkit-transition: all 0.3s;
            -o-transition: all 0.3s;
            -moz-transition: all 0.3s;
            transition: all 0.3s;
        }

        /*.fixed-header .wrap_header {
            height: auto;
                }*/


                /*[ Logo ]
                -----------------------------------------------------------*/
                .logo {
                    display: block;
                    position: absolute;
                    left: 52px;
                    top: 50%;
                    -webkit-transform: translateY(-50%);
                    -moz-transform: translateY(-50%);
                    -ms-transform: translateY(-50%);
                    -o-transform: translateY(-50%);
                    transform: translateY(-50%);
                }

                .logo img {
                    width: 242px;
                    height: 45px;
                }

                /*.logo img {
            max-height: 27px;
                }*/
                .list1 li {
                    display: block;
                    font-size: 14px;
                    line-height: 20px;
                    padding: 11px 0 5px 32px;
                    background: url(../images/list_li.png) 0 12px no-repeat;
                }

                .list2 li {
                    display: block;
                    font-size: 14px;
                    line-height: 20px;
                    padding: 11px 0 5px 32px;
                    background: url(../images/marker_1.png) 0 12px no-repeat;
                }

                /*[ Menu ]
                -----------------------------------------------------------*/
                .main_menu {
                    list-style-type: none;
                    margin: 0px;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: center;
                }

                .main_menu>li {
                    display: block;
                    position: relative;
                    padding-top: 20px;
                    padding-bottom: 20px;
                    padding-left: 15px;
                    padding-right: 15px;
                }

                .main_menu>li>a {
                    font-family: Montserrat-Regular;
                    font-size: 15px;
                    color: #333333;
                    padding: 0;
                    border-bottom: 1px solid transparent;
                }

                li.sale-noti>a {
                    color: #d40900;
                }

                .main_menu>li:hover>a {
                    text-decoration: none;
                    border-bottom: 3px solid #333333;
                }

                .main_menu li {
                    position: relative;
                }

                .main_menu>li:hover>.sub_menu {
                    visibility: visible;
                    opacity: 1;
                }

                .sub_menu {
                    list-style-type: none;
                    position: absolute;
                    z-index: 1100;
                    top: 0;
                    left: 100%;
                    width: 225px;
                    background-color: #222222;
                    opacity: 0;
                    visibility: hidden;
                    padding-top: 10px;
                    padding-bottom: 10px;
                    transition: all 0.4s;
                    -webkit-transition: all 0.4s;
                    -o-transition: all 0.4s;
                    -moz-transition: all 0.4s;
                }

                .main_menu>li>.sub_menu {
                    top: 100%;
                    left: 0;
                    position: absolute;
                }

                .sub_menu li:hover>.sub_menu {
                    visibility: visible;
                    opacity: 1;
                }

                .sub_menu li {
                    transition: all 0.3s;
                    -webkit-transition: all 0.3s;
                    -o-transition: all 0.3s;
                    -moz-transition: all 0.3s;
                }

                .sub_menu li,
                .sub_menu a {
                    padding: 10px;
                    font-family: Montserrat-Regular;
                    font-size: 13px;
                    color: white;
                }

                .sub_menu>li:hover>a {
                    color: #e65540;
                    text-decoration: none;
                }

                /* ------------------------------------ */
                .header-icons {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    align-items: center;
                    position: absolute;
                    right: 52px;
                    top: 50%;
                    -webkit-transform: translateY(-50%);
                    -moz-transform: translateY(-50%);
                    -ms-transform: translateY(-50%);
                    -o-transform: translateY(-50%);
                    transform: translateY(-50%);
                }

                .header-wrapicon1,
                .header-wrapicon2 {
                    height: 27px;
                    position: relative;
                }

                .header-wrapicon1 img,
                .header-wrapicon2 img {
                    height: 100%;
                }

                .header-icon1:hover,
                .header-icon2:hover {
                    cursor: pointer;
                }

                .header-icons-noti {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background-color: #111111;
                    color: white;
                    font-family: Montserrat-Medium;
                    font-size: 12px;
                    position: absolute;
                    top: 0;
                    right: -10px;
                }

                .linedivide1 {
                    display: block;
                    height: 20px;
                    width: 1px;
                    background-color: #e5e5e5;
                    margin-left: 23px;
                    margin-right: 23px;
                    margin-top: 5px;
                }

                /*[ Header cart ]
                -----------------------------------------------------------
                */
                .header-cart {
                    position: absolute;
                    z-index: 1100;
                    width: 339px;
                    top: 190%;
                    right: -10px;
                    padding: 20px;
                    border-top: 3px solid #e6e6e6;
                    background-color: white;

                    box-shadow: 0 3px 5px 0px rgba(0, 0, 0, 0.1);
                    -moz-box-shadow: 0 3px 5px 0px rgba(0, 0, 0, 0.1);
                    -webkit-box-shadow: 0 3px 5px 0px rgba(0, 0, 0, 0.1);
                    -o-box-shadow: 0 3px 5px 0px rgba(0, 0, 0, 0.1);
                    -ms-box-shadow: 0 3px 5px 0px rgba(0, 0, 0, 0.1);

                    transition: all 0.3s;
                    -webkit-transition: all 0.3s;
                    -o-transition: all 0.3s;
                    -moz-transition: all 0.3s;

                    transform-origin: top right;
                    -webkit-transform: scale(0);
                    -moz-transform: scale(0);
                    -ms-transform: scale(0);
                    -o-transform: scale(0);
                    transform: scale(0);
                }

                .show-header-dropdown {
                    -webkit-transform: scale(1);
                    -moz-transform: scale(1);
                    -ms-transform: scale(1);
                    -o-transform: scale(1);
                    transform: scale(1);
                }

                .fixed-header .header-cart {
                    top: 160%;
                }

                .header-cart-wrapitem {
                    max-height: 270px;
                    overflow: auto;
                }

                .header-cart-item {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    padding-bottom: 5px;
                    padding-top: 5px;
                }

                /* ------------------------------------ */
                .header-cart-item-img {
                    width: 80px;
                    position: relative;
                    margin-right: 20px;
                }

                .header-cart-item-img img {
                    width: 100%;
                }

                .header-cart-item-img::after {
                    content: '\e870';
                    font-family: Linearicons;
                    font-size: 16px;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    top: 0;
                    left: 0;
                    background-color: rgba(0, 0, 0, 0.5);
                    color: white;
                    transition: all 0.3s;
                    -webkit-transition: all 0.3s;
                    -o-transition: all 0.3s;
                    -moz-transition: all 0.3s;
                    opacity: 0;
                }

                .header-cart-item-img:hover:after {
                    cursor: pointer;
                    opacity: 1;
                }

                /* ------------------------------------ */
                .header-cart-item-txt {
                    width: calc(100% - 100px);
                }

                .header-cart-item-name {
                    display: block;
                    font-family: Montserrat-Regular;
                    font-size: 15px;
                    color: #555555;
                    line-height: 1.3;
                    margin-bottom: 12px;
                }

                .header-cart-item-info {
                    display: block;
                    font-family: Montserrat-Regular;
                    font-size: 12px;
                    color: #888888;
                    line-height: 1.5;
                }

                .header-cart-total {
                    font-family: Montserrat-Regular;
                    font-size: 15px;
                    color: #555555;
                    line-height: 1.3;
                    text-align: right;
                    padding-top: 15px;
                    padding-bottom: 25px;
                    padding-right: 3px;
                }

                /* ------------------------------------ */
                .header-cart-buttons {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: space-between;
                    align-items: center;
                }

                .header-cart-wrapbtn {
                    width: calc((100% - 10px) / 2);
                }



                /*[ Header Mobile ]
                >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/
                .wrap_header_mobile {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    min-height: 80px;
                    padding-left: 20px;
                    padding-top: 10px;
                    padding-bottom: 10px;
                    background-color: white;
                    display: none;
                }

                /*[ Logo mobile ]
                -----------------------------------------------------------*/
                .logo-mobile {
                    display: block;
                }

                .logo-mobile img {
                    max-height: 64px;
                }

                /*[ btn show menu ]
                -----------------------------------------------------------*/
                .btn-show-menu {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    height: 100%;
                    justify-content: center;
                    align-items: center;
                }

                .hamburger {
                    -webkit-transform: scale(0.8);
                    -moz-transform: scale(0.8);
                    -ms-transform: scale(0.8);
                    -o-transform: scale(0.8);
                    transform: scale(0.8);
                    margin-top: 5px;
                }


                /*[ Header icon mobile ]
                -----------------------------------------------------------*/
                .header-icons-mobile {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    align-items: center;
                    margin-right: 15px;
                }

                .linedivide2 {
                    display: block;
                    height: 20px;
                    width: 1px;
                    margin-left: 10px;
                    margin-right: 10px;
                    margin-top: 5px;
                }

                .header-icons-mobile .header-cart {
                    width: 300px;
                    top: 190%;
                    right: -80px;
                    z-index: 1100;
                    transform-origin: top right;
                }

                /*[ Menu mobile ]
                -----------------------------------------------------------*/
                .wrap-side-menu {
                    width: 100%;
                    background-color: white;
                    display: none;
                    border-top: 1px solid #ececec;
                }

                .side-menu {
                    width: 100%;
                }

                .side-menu li {
                    list-style-type: none;
                }

                .side-menu .main-menu {
                    margin-bottom: 0;
                }

                .item-menu-mobile {
                    background-color: #2828f9;
                }

                .side-menu .main-menu>li>a {
                    padding-left: 20px;
                    font-family: Montserrat-Regular;
                    font-size: 15px;
                    color: white;
                    line-height: 2.86;
                }

                .side-menu .main-menu>li {
                    color: white;
                    position: relative;
                }


                .side-menu .main-menu .arrow-main-menu {
                    font-size: 14px;
                    position: absolute;
                    right: 20px;
                    top: 5px;
                    padding: 10px;
                    -webkit-transition: all 0.4s !important;
                    -o-transition: all 0.4s !important;
                    -moz-transition: all 0.4s !important;
                    transition: all 0.4s !important;
                }

                .side-menu .main-menu .arrow-main-menu:hover {
                    cursor: pointer;
                }

                .turn-arrow {
                    -webkit-transform: rotate(90deg);
                    -moz-transform: rotate(90deg);
                    -ms-transform: rotate(90deg);
                    -o-transform: rotate(90deg);
                    transform: rotate(90deg);
                }

                .side-menu .sub-menu a {
                    padding-left: 20px;
                    font-family: Montserrat-Regular;
                    font-size: 13px;
                    color: #333333;
                    line-height: 2.5;
                }

                .side-menu .sub-menu>li {
                    padding-left: 12px;
                    padding-top:
                }

                .side-menu .sub-menu a:hover {
                    text-decoration: none;
                    padding-left: 20px;
                    color: #e65540 !important;
                }

                .side-menu .sub-menu {
                    background-color: white;
                    display: none;
                }

                @media (min-width: 992px) {
                    .wrap-side-menu {
                        display: none;
                    }
                }

                /* ------------------------------------ */
                .item-topbar-mobile {
                    border-bottom: 1px solid #ececec;
                }

                .topbar-child2-mobile {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                }

                .topbar-social-moblie {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    align-items: center;
                }


                /*[ Header2 ]
                ///////////////////////////////////////////////////////////
                */
                .topbar2 {
                    background-color: #fff;
                    position: relative;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }

                /* ------------------------------------ */
                .logo2 {
                    display: block;
                }

                .logo2 img {
                    max-height: 27px;
                }

                .fixed-header2 {
                    z-index: 1300;
                    position: fixed;
                    height: 65px;
                    left: 0;
                    top: -70px;
                    visibility: hidden;

                    box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
                    -moz-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
                    -webkit-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
                    -o-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
                    -ms-box-shadow: 0 1px 5px 0px rgba(0, 0, 0, 0.2);
                }

                .fixed-header2 .header-cart {
                    top: 160%;
                }

                .show-fixed-header2 {
                    visibility: visible;
                    top: 0px;
                }


                /*[ Header3 ]
                ///////////////////////////////////////////////////////////
                */
                .container-menu-header-v3 {
                    position: fixed;
                    z-index: 1200;
                    top: 0;
                    left: 0;
                    background-color: #fff;
                    width: 320px;
                    height: 100vh;
                    border-right: 1px solid #e5e5e7;

                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: space-between;
                    flex-wrap: wrap;
                }

                /*[ Menu ]
                ===========================================================*/
                .container-menu-header-v3 .wrap_header {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    flex-wrap: wrap;
                    width: 100%;
                    background-color: white;
                }


                /*[ Logo ]
                -----------------------------------------------------------*/
                .container-menu-header-v3 .logo3 {
                    display: block;
                }

                .container-menu-header-v3 .logo3 img {
                    max-width: 120px;
                }

                /*[ Header Icon ]
                -----------------------------------------------------------*/
                .container-menu-header-v3 .header-icons3 {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    align-items: center;
                    position: unset;
                }

                /*[ Header cart ]
                -----------------------------------------------------------
                */
                .container-menu-header-v3 .header-cart {
                    left: -10px;
                    transform-origin: top left;
                }

                /*[ Menu ]
                -----------------------------------------------------------*/
                .container-menu-header-v3 .main_menu {
                    list-style-type: none;
                    margin: 0px;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-wrap: wrap;
                    align-items: stretch;
                    justify-content: center;
                    flex-direction: column;
                }

                .container-menu-header-v3 .main_menu>li {
                    padding-top: 3px;
                    padding-bottom: 3px;
                    padding-left: 0px;
                    padding-right: 0px;
                    text-align: center;
                }

                .container-menu-header-v3 .sub_menu {
                    top: 0;
                    left: 100%;
                }

                .container-menu-header-v3 .main_menu>li>.sub_menu {
                    top: 10px;
                    left: 95%;
                }

                .container-menu-header-v3 .sub_menu li {
                    text-align: left;
                }

                .container-menu-header-v3 .topbar-social-item {
                    padding: 10px 8px;
                }


                /*[ Page sidebar ]
                -----------------------------------------------------------
                */
                .container1-page {
                    margin-left: 320px;
                }

                @media (max-width: 992px) {
                    .wrap_header_mobile {
                        padding-bottom: 0px !important;
                        padding-top: 0px !important;
                        display: -webkit-box;
                        display: -webkit-flex;
                        display: -moz-box;
                        display: -ms-flexbox;
                        display: flex !important;
                    }

                    .wrap_header {
                        display: none;
                    }

                    .container-menu-header-v3,
                    .container-menu-header-v2,
                    .container-menu-header {
                        display: none;
                    }

                    .top-bar {
                        display: none;
                    }

                    header {
                        height: auto !important;
                    }

                    .container1-page {
                        margin-left: 0px;
                    }
                }



                /*[ Slide1 ]
                ///////////////////////////////////////////////////////////
                */

                /*[ Slick1 ]
                -----------------------------------------------------------
                */
                .wrap-slick1 {
                    position: relative;
                }

                .item-slick1 {
                    height: 570px;
                    background-size: cover;
                    background-repeat: no-repeat;
                    background-position: center center;
                }

                .arrow-slick1 {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    width: 40px;
                    height: 40px;
                    font-size: 18px;
                    color: white;
                    position: absolute;
                    background-color: black;
                    opacity: 0;

                    top: 50%;
                    -webkit-transform: translateY(-50%);
                    -moz-transform: translateY(-50%);
                    -ms-transform: translateY(-50%);
                    -o-transform: translateY(-50%);
                    transform: translateY(-50%);

                    border-radius: 50%;
                    z-index: 200;
                    -webkit-transition: all 0.4s;
                    -o-transition: all 0.4s;
                    -moz-transition: all 0.4s;
                    transition: all 0.4s;
                }

                .wrap-slick1:hover .arrow-slick1 {
                    opacity: 0.5;
                }

                .arrow-slick1:hover {
                    background-color: #e65540;
                }

                .next-slick1 {
                    right: 50px;
                    left: auto;
                }

                .prev-slick1 {
                    left: 50px;
                    right: auto;
                }

                @media (max-width: 576px) {
                    .next-slick1 {
                        right: 15px;
                    }

                    .prev-slick1 {
                        left: 15px;
                    }
                }

                /*[ Caption ]
                -----------------------------------------------------------
                */
                @media (max-width: 992px) {
                    .wrap-content-slide1 .xl-text2 {
                        font-size: 60px;
                    }
                }

                @media (max-width: 768px) {

                    .wrap-content-slide1 .xl-text3,
                    .wrap-content-slide1 .xl-text2,
                    .wrap-content-slide1 .xl-text1 {
                        font-size: 50px;
                    }

                    .wrap-content-slide1 .m-text27,
                    .wrap-content-slide1 .m-text1 {
                        font-size: 16px;
                    }

                    .item-slick1 {
                        height: 470px;
                    }
                }

                @media (max-width: 576px) {

                    .wrap-content-slide1 .xl-text3,
                    .wrap-content-slide1 .xl-text2,
                    .wrap-content-slide1 .xl-text1 {
                        font-size: 40px;
                    }

                    .wrap-content-slide1 .m-text27,
                    .wrap-content-slide1 .m-text1 {
                        font-size: 16px;
                    }

                    .item-slick1 {
                        height: 370px;
                    }
                }

                /*[ rs1-slick1 ]
                -----------------------------------------------------------
                */
                .rs1-slick1 .item-slick1 {
                    height: 100vh;
                }

                @media (max-width: 992px) {
                    .rs1-slick1 .item-slick1 {
                        height: calc(100vh - 85px);
                    }
                }




                /*[ Slide2 ]
                ///////////////////////////////////////////////////////////
                */

                /*[ Slick2 ]
                -----------------------------------------------------------
                */
                .wrap-slick2 {
                    position: relative;
                    margin-right: -15px;
                    margin-left: -15px;
                }

                /* ------------------------------------ */
                .arrow-slick2 {
                    position: absolute;
                    z-index: 100;
                    top: calc((100% - 70px) / 2);
                    -webkit-transform: translateY(-50%);
                    -moz-transform: translateY(-50%);
                    -ms-transform: translateY(-50%);
                    -o-transform: translateY(-50%);
                    transform: translateY(-50%);
                    font-size: 39px;
                    color: #cccccc;

                    -webkit-transition: all 0.4s;
                    -o-transition: all 0.4s;
                    -moz-transition: all 0.4s;
                    transition: all 0.4s;
                }

                .arrow-slick2:hover {
                    color: #666666;
                }

                .next-slick2 {
                    right: -30px;
                }

                .prev-slick2 {
                    left: -30px;
                }

                @media (max-width: 1280px) {
                    .next-slick2 {
                        right: 0px;
                    }

                    .prev-slick2 {
                        left: 0px;
                    }
                }

                @media (max-width: 1610px) {
                    .rs1-slick2 .next-slick2 {
                        right: 0px;
                    }

                    .rs1-slick2 .prev-slick2 {
                        left: 0px;
                    }
                }

                /*[ rs Sweetalert ]
                ///////////////////////////////////////////////////////////
                */
                .swal-overlay {
                    overflow-y: auto;
                }

                .swal-icon--success {
                    border-color: #66a8a6;
                }

                .swal-icon--success__line {
                    background-color: #66a8a6;
                }

                .swal-icon--success__ring {
                    border: 4px solid rgba(102, 168, 166, 0.2);
                }

                .swal-button:focus {
                    outline: none;
                    box-shadow: none;
                }

                .swal-button {
                    background-color: #e65540;
                    font-family: Montserrat-Regular;
                    font-size: 15px;
                    color: white;
                    text-transform: uppercase;
                    font-weight: unset;
                    border-radius: 20px;
                    -webkit-transition: all 0.3s;
                    -o-transition: all 0.3s;
                    -moz-transition: all 0.3s;
                    transition: all 0.3s;
                }

                .swal-button:hover {
                    background-color: #333333;
                }

                .swal-button:active {
                    background-color: #e65540;
                }

                .swal-title {
                    font-family: Montserrat-Medium;
                    color: #333333;
                    font-size: 16px;
                    line-height: 1.5;
                    padding: 0 15px;
                }

                .swal-text {
                    font-family: Montserrat-Regular;
                    color: #333333;
                    font-size: 15px;
                    text-align: center;
                }

                .swal-footer {
                    margin-top: 0;
                }


                /*[ Block1 ]
                ///////////////////////////////////////////////////////////
                */
                .block1-wrapbtn {
                    position: absolute;
                    left: 50%;
                    -webkit-transform: translateX(-50%);
                    -moz-transform: translateX(-50%);
                    -ms-transform: translateX(-50%);
                    -o-transform: translateX(-50%);
                    transform: translateX(-50%);
                    bottom: 20px;

                    box-shadow: 0 1px 3px 0px rgba(0, 0, 0, 0.1);
                    -moz-box-shadow: 0 1px 3px 0px rgba(0, 0, 0, 0.1);
                    -webkit-box-shadow: 0 1px 3px 0px rgba(0, 0, 0, 0.1);
                    -o-box-shadow: 0 1px 3px 0px rgba(0, 0, 0, 0.1);
                    -ms-box-shadow: 0 1px 3px 0px rgba(0, 0, 0, 0.1);
                }


                /*[ Block2 ]
                ///////////////////////////////////////////////////////////
                */
                .block2-labelsale::before,
                .block2-labelnew::before {
                    z-index: 100;
                    font-family: Montserrat-Regular;
                    font-size: 12px;
                    color: white;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    width: 50px;
                    height: 22px;
                    border-radius: 11px;
                    position: absolute;
                    top: 12px;
                    left: 12px;
                }

                .block2-labelsale::before {
                    background-color: #e65540;
                    content: 'Sale';
                }

                .block2-labelnew::before {
                    background-color: #66a8a6;
                    content: 'New';
                }

                /* ------------------------------------ */
                .block2-overlay {
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    top: 0;
                    left: 0;
                    background-color: rgba(0, 0, 0, 0.05);
                    opacity: 0;
                    border-radius: 5px;
                    border: 1px solid #fff;
                }

                /* ------------------------------------ */
                .block2-btn-addcart {
                    position: absolute;
                    left: 50%;
                    -webkit-transform: translateX(-50%);
                    -moz-transform: translateX(-50%);
                    -ms-transform: translateX(-50%);
                    -o-transform: translateX(-50%);
                    transform: translateX(-50%);
                    bottom: -45px;
                }

                /* ------------------------------------ */
                .block2-btn-towishlist,
                .block2-btn-addwishlist {
                    display: block;
                    position: absolute;
                    top: 26px;
                    right: 20px;
                    font-size: 20px;
                    color: white;
                    line-height: 0;
                    -webkit-transform: scale(0);
                    -moz-transform: scale(0);
                    -ms-transform: scale(0);
                    -o-transform: scale(0);
                    transform: scale(0);
                }

                .block2-btn-addwishlist:hover {
                    color: white;
                }

                .block2-btn-addwishlist .icon-wishlist,
                .block2-btn-towishlist .icon-wishlist {
                    line-height: 0;
                }

                .block2-btn-addwishlist:hover .icon_heart_alt {
                    display: none;
                }

                .block2-btn-addwishlist:hover .icon_heart {
                    display: block;
                }

                /* ------------------------------------ */
                .block2-btn-towishlist .icon_heart_alt {
                    display: none;
                }

                .block2-btn-towishlist .icon_heart {
                    display: block;
                    color: #e65540;
                }

                /* ------------------------------------ */
                .block2-overlay:hover {
                    opacity: 1;
                }

                .block2-overlay:hover .block2-btn-addcart {
                    bottom: 20px;
                }

                .block2-overlay:hover .block2-btn-addwishlist,
                .block2-overlay:hover .block2-btn-towishlist {
                    -webkit-transform: scale(1);
                    -moz-transform: scale(1);
                    -ms-transform: scale(1);
                    -o-transform: scale(1);
                    transform: scale(1);
                }


                /*[ Block4 ]
                ///////////////////////////////////////////////////////////
                */
                .block4 {
                    position: relative;
                    overflow: hidden;
                    width: calc(100% / 5);
                }

                @media (max-width: 1360px) {
                    .block4 {
                        width: calc(100% / 4);
                    }
                }

                @media (max-width: 1200px) {
                    .block4 {
                        width: calc(100% / 3);
                    }
                }

                @media (max-width: 992px) {
                    .block4 {
                        width: calc(100% / 2);
                    }
                }

                @media (max-width: 576px) {
                    .block4 {
                        width: calc(100% / 1);
                    }
                }

                /* ------------------------------------ */
                @media (max-width: 1660px) {
                    .rs1-block4 .block4 {
                        width: calc(100% / 4);
                    }
                }

                @media (max-width: 1380px) {
                    .rs1-block4 .block4 {
                        width: calc(100% / 3);
                    }
                }

                @media (max-width: 1200px) {
                    .rs1-block4 .block4 {
                        width: calc(100% / 2);
                    }
                }

                @media (max-width: 576px) {
                    .rs1-block4 .block4 {
                        width: calc(100% / 1);
                    }
                }

                /* ------------------------------------ */
                .block4-overlay {
                    display: block;
                    background-color: rgba(0, 0, 0, 0.9);
                    visibility: hidden;
                    opacity: 0;
                }

                .block4-overlay:hover {
                    color: unset;
                }

                /* ------------------------------------ */
                .block4-overlay-txt {
                    position: absolute;
                    width: 100%;
                    left: 0;
                    bottom: -100%;
                }

                /* ------------------------------------ */
                .block4-overlay-heart {
                    transform-origin: top left;
                    -webkit-transform: scale(0);
                    -moz-transform: scale(0);
                    -ms-transform: scale(0);
                    -o-transform: scale(0);
                    transform: scale(0);
                }

                /* ------------------------------------ */
                .block4:hover .block4-overlay {
                    visibility: visible;
                    opacity: 1;
                }

                .block4:hover .block4-overlay-txt {
                    bottom: 0;
                }

                .block4:hover .block4-overlay-heart {
                    -webkit-transform: scale(1);
                    -moz-transform: scale(1);
                    -ms-transform: scale(1);
                    -o-transform: scale(1);
                    transform: scale(1);
                }


                /*[ BG Title Page ]
                ///////////////////////////////////////////////////////////
                */
                .bg-title-page {
                    width: 100%;
                    //min-height: 239px;
                    /* padding-left: 15px;
            padding-right: 15px; */
                    background-repeat: no-repeat;
                    background-position: center 0;
                    background-size: cover;
                }

                @media (max-width: 576px) {
                    .bg-title-page .l-text2 {
                        font-size: 35px;
                    }

                    .bg-title-page .m-text13 {
                        font-size: 16px;
                    }
                }

                /*[ rs NoUI ]
                ///////////////////////////////////////////////////////////
                */
                .leftbar #filter-bar {
                    margin-right: 6px;
                    margin-left: 6px;
                    height: 4px;
                    border: none;
                    background-color: #e1e1e1;
                }

                .leftbar #filter-bar .noUi-connect {
                    background-color: #c5c5c5;
                    border: none;
                    box-shadow: none;
                }

                .leftbar #filter-bar .noUi-handle {
                    width: 13px;
                    height: 13px;
                    left: -6px;
                    top: -5px;
                    border: none;
                    border-radius: 50%;
                    background: #999999;
                    cursor: pointer;
                    box-shadow: none;
                    outline: none;
                }

                .leftbar #filter-bar .noUi-handle:before {
                    display: none;
                }

                .leftbar #filter-bar .noUi-handle:after {
                    display: none;
                }

                /*[ Filter Color ]
                ///////////////////////////////////////////////////////////
                */
                .color-filter1 {
                    background-color: #00bbec;
                }

                .color-filter2 {
                    background-color: #2c6ed5;
                }

                .color-filter3 {
                    background-color: #ffa037;
                }

                .color-filter4 {
                    background-color: #ff5337;
                }

                .color-filter5 {
                    background-color: #a88c77;
                }

                .color-filter6 {
                    background-color: #393939;
                }

                .color-filter7 {
                    background-color: #cccccc;
                }

                .checkbox-color-filter {
                    display: none;
                }

                .color-filter {
                    display: block;
                    width: 25px;
                    height: 25px;
                    cursor: pointer;
                    border-radius: 50%;
                }

                .checkbox-color-filter:checked+.color-filter {
                    box-shadow: 0 0 0px 2px black;
                    -moz-box-shadow: 0 0 0px 2px black;
                    -webkit-box-shadow: 0 0 0px 2px black;
                    -o-box-shadow: 0 0 0px 2px black;
                    -ms-box-shadow: 0 0 0px 2px black;
                }

                /*[ Pagination ]
                ///////////////////////////////////////////////////////////
                */
                .pagination {
                    margin-right: -6px;
                    margin-left: -6px;
                }

                .item-pagination {
                    font-family: Montserrat-Regular;
                    font-size: 13px;
                    color: #808080;
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    border: 1px solid #eeeeee;
                    margin: 6px;
                }

                .item-pagination:hover {
                    background-color: #222222;
                    color: white;
                }

                .active-pagination {
                    background-color: #222222;
                    color: white;
                }


                /*[ Slick3 ]
                ///////////////////////////////////////////////////////////
                */

                .wrap-slick3-dots {
                    width: 14.5%;
                }

                .slick3 {
                    width: 80.64%;
                }

                .slick3-dots li {
                    display: block;
                    position: relative;
                    width: 100%;
                    margin-bottom: 15px;
                }

                .slick3-dots li img {
                    width: 100%;
                }

                .slick3-dot-overlay {
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    top: 0;
                    left: 0;
                    cursor: pointer;
                    border: 3px solid transparent;
                    -webkit-transition: all 0.4s;
                    -o-transition: all 0.4s;
                    -moz-transition: all 0.4s;
                    transition: all 0.4s;
                }

                .slick3-dot-overlay:hover {
                    border: 3px solid #888888;
                }

                .slick3-dots .slick-active .slick3-dot-overlay {
                    border: 1px solid #ccc;
                }

                .t-a-top {
                    margin-top: 25%;
                }


                /*[ Dropdown content ]
                ///////////////////////////////////////////////////////////
                */
                .show-dropdown-content .down-mark {
                    display: block;
                }

                .show-dropdown-content .up-mark {
                    display: none;
                }


                /*[ Cart ]
                ///////////////////////////////////////////////////////////
                */
                /*[ Table ]
                -----------------------------------------------------------
                */
                .wrap-table-shopping-cart {
                    overflow: auto;
                }

                .container-table-cart::before {
                    content: '';
                    display: block;
                    position: absolute;
                    width: 1px;
                    height: calc(100% - 51px);
                    background-color: #e6e6e6;
                    top: 51px;
                    left: 0;
                }

                .container-table-cart::after {
                    content: '';
                    display: block;
                    position: absolute;
                    width: 1px;
                    height: calc(100% - 51px);
                    background-color: #e6e6e6;
                    top: 51px;
                    right: 0;
                }

                .table-shopping-cart {
                    border-collapse: collapse;
                    width: 100%;
                    min-width: 992px;
                }

                .table-shopping-cart .table-row {
                    border-top: 1px solid #e6e6e6;
                    border-bottom: 1px solid #e6e6e6;
                }

                .table-shopping-cart .column-1 {
                    width: 225px;
                    padding-left: 50px;
                }

                .table-shopping-cart .column-2 {
                    width: 330px;
                    padding-right: 30px;
                }

                .table-shopping-cart .column-3 {
                    width: 133px;
                    padding-right: 30px;
                }

                .table-shopping-cart .column-4 {
                    width: 355px;
                    padding-right: 30px;
                }

                .table-shopping-cart .column-5 {
                    padding-right: 30px;
                }

                .table-shopping-cart .table-head th {
                    font-family: Montserrat-Bold;
                    font-size: 13px;
                    color: #555555;
                    line-height: 1.5;
                    text-transform: uppercase;
                    padding-top: 16px;
                    padding-bottom: 16px;
                }

                .table-shopping-cart td {
                    font-family: Montserrat-Regular;
                    font-size: 16px;
                    color: #555555;
                    line-height: 1.5;
                    padding-top: 37px;
                    padding-bottom: 30px;
                }

                .table-shopping-cart .table-row .column-2 {
                    font-size: 15px;
                }


                /* ------------------------------------ */
                .cart-img-product {
                    width: 90px;
                    position: relative;
                }

                .cart-img-product img {
                    width: 100%;
                }

                .cart-img-product::after {
                    content: '\e870';
                    font-family: Linearicons;
                    font-size: 16px;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    top: 0;
                    left: 0;
                    background-color: rgba(0, 0, 0, 0.5);
                    color: white;
                    transition: all 0.3s;
                    -webkit-transition: all 0.3s;
                    -o-transition: all 0.3s;
                    -moz-transition: all 0.3s;
                    opacity: 0;
                }

                .cart-img-product:hover:after {
                    cursor: pointer;
                    opacity: 1;
                }


                /*[ Tags ]
                ///////////////////////////////////////////////////////////
                */
                .wrap-tags {
                    margin-right: -3px;
                    margin-left: -3px;
                }

                .tag-item {
                    display: block;
                    font-family: Montserrat-Regular;
                    font-size: 13px;
                    color: #888888;
                    line-height: 1.5;
                    padding: 5px 15px;
                    border: 1px solid #cccccc;
                    border-radius: 15px;
                    margin: 3px;
                }

                .tag-item:hover {
                    border: 1px solid #e65540;
                }


                /*[ tab01 ]
                ///////////////////////////////////////////////////////////
                */
                .tab01 .nav-tabs {
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                    align-items: center;
                    background-color: #fff;
                    border-bottom: none;
                    margin-right: -15px;
                    margin-left: -15px;
                }

                .tab01 .nav-tabs .nav-item {
                    padding: 8px 16px;
                }

                .tab01 .nav-link {
                    padding: 0;
                    border-radius: 0px;
                    border: none;
                    border-bottom: 1px solid transparent;
                    font-family: Montserrat-Regular;
                    font-size: 15px;
                    color: #888888;
                    line-height: 1.1;
                }

                .tab01 .nav-link.active {
                    color: #333333;
                    border-bottom: 1px solid #6a6a6a;
                }

                .tab01 .nav-link:hover {
                    color: #333333;
                    border-bottom: 1px solid #6a6a6a;
                }

                @media (max-width: 480px) {
                    .tab01 .nav-tabs .nav-item {
                        padding: 8px 6px;
                    }

                    .tab01 .nav-tabs {
                        margin-right: -6px;
                        margin-left: -6px;
                    }
                }


                /*[ Modal video 01 ]
                ///////////////////////////////////////////////////////////
                */
                body {
                    padding-right: 0px !important;
                }

                .modal {
                    padding: 0px !important;
                    z-index: 1360;
                    overflow-x: hidden;
                    overflow-y: auto !important;
                }

                .modal-open {
                    overflow-y: scroll;
                }

                /* ------------------------------------ */
                .modal-backdrop {
                    background-color: transparent;
                }

                #modal-video-01 {
                    background-color: rgba(0, 0, 0, 0.8);
                    z-index: 1350;

                }

                #modal-video-01 .modal-dialog {
                    max-width: 100%;
                    height: 100%;
                    padding: 0;
                    margin: 0;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                    align-items: center;
                    position: relative;
                }

                .wrap-video-mo-01 {
                    width: 854px;
                    height: auto;
                    position: relative;
                    margin: 15px;
                }

                .video-mo-01 {
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    top: 0;
                    left: 0;
                    opacity: 0;
                    -webkit-transition: all 2s;
                    -o-transition: all 2s;
                    -moz-transition: all 2s;
                    transition: all 2s;
                }

                .video-mo-01 iframe {
                    width: 100%;
                    height: 100%;
                }

                .close-mo-video-01 {
                    font-size: 50px;
                    color: white;
                    opacity: 0.6;
                    display: -webkit-box;
                    display: -webkit-flex;
                    display: -moz-box;
                    display: -ms-flexbox;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: absolute;
                    z-index: 1250;
                    width: 60px;
                    height: 60px;
                    top: 0;
                    right: 0;
                }

                .close-mo-video-01:hover {
                    cursor: pointer;
                    opacity: 1;
                }


                /*[ Input NumProduct ]
                ///////////////////////////////////////////////////////////
                */
                input.num-product {
                    -moz-appearance: textfield;
                    appearance: none;
                    -webkit-appearance: none;
                }

                input.num-product::-webkit-outer-spin-button,
                input.num-product::-webkit-inner-spin-button {
                    -webkit-appearance: none;
                    margin: 0;
                }

                .categoreies {
                    position: relative;
                    top: 0em;
                    /*margin: 0 auto; */
                    left: 0em;
                }

                .feature_contain {
                    position: relative;
                    /*top:-6em;*/
                    margin: 0 auto;
                }

                .blog_contain {
                    position: relative;
                    top: -3em;
                }

                .ad_pad {
                    padding: 1em 0;
                }

                .feature_contain_big {
                    width: 66%;
                    height: auto;
                    overflow: hidden;
                    border: solid 0px red;
                    clear: both;
                    position: relative;
                    margin: 0 auto;
                }

                .sec_big {
                    background-color: #fff;
                    margin-bottom: 2em;
                    position: relative;
                    top: -3em;
                    margin: 0 auto;
                    width: 62%;
                    left: -0.5em;
                }

                .row_1 {
                    margin-left: -2.6em !important;
                    margin-right: 0 !important;
                }

                @media only screen and (max-width: 1920px) {
                    .feature_contain_big {
                        width: 93%;
                    }

                    .sec_big {
                        width: 79%;
                    }

                    .pro_ipho {
                        margin-right: 2.5em;
                    }

                    .item-slick1 {
                        height: 310px;
                    }
                }

                @media only screen and (max-width: 380px) {
                    .feature_contain_big {
                        width: 100%;
                    }

                    .item-slick1 {
                        height: 282px;
                    }

                    .pro_gal {
                        margin-right: 1.4em;
                    }
                }

                @media only screen and (max-width: 360px) {
                    .pro_ipho {
                        margin-right: 0.42em;
                    }
                }

                @media only screen and (min-width: 390px) and (max-width: 411px) {
                    .feature_contain_big {
                        width: 93%;
                    }

                    .pro_ipho {
                        margin-right: 2em;
                    }

                    .item-slick1 {
                        height: 310px
                    }
                }

                @media only screen and (min-device-width : 320px) and (max-device-width : 480px) {

                    .page1_wrapper {
                        position: relative;
                        margin: 0 auto;
                        width: 98%;
                    }

                    .page1 {
                        background-image: url(../images/f-images/top_code_bg.png);
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .page2 {
                        background-image: url(../images/f-images/kids_special_bg.png);
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .page3 {
                        background-image: url(../images/new_products/fountain_bg.png);
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                    }

                    .page4 {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .hai_page_bg {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .two_box {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                        float: left;
                    }

                    .m_popup {
                        max-width: 49%;
                        float: left;
                    }

                    .m_million {
                        max-width: 49%;
                        float: right;
                    }

                    .big_sale {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .m_new_arrival {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .m_spinner {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .m_featured {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .m_popcorn {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .m_flowerpots {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .m_newyear_pro {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .page5 {
                        background-image: url(../images/new_products/fancy_bg.png);
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                    }

                    .page6 {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                        margin-bottom: 1em;
                    }

                    .container-fuield img.mobile_img {
                        height: 20em !important;
                        width: 100% !important;
                        padding: 10px !important;
                    }

                    .page7 {
                        background-image: url(../images/new_products/night_bg.png);
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                    }

                    .page8 {
                        background-image: none;
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                    }

                    .page8_1 {
                        background-image: url(../images/f-images/gelly.png);
                        background-repeat: no-repeat;
                        max-width: 100%;
                        height: auto;
                    }

                    img {
                        height: auto;
                    }

                    .topcode_one {
                        top: 2em;
                        left: 1em;
                        position: relative;
                    }

                    .topcode_two {
                        position: relative;
                        text-align: right;
                        bottom: 3em;
                    }

                    .handlight_text {
                        font-family: Tahoma, Geneva, sans-serif;
                        color: #866032;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .kids_text {
                        font-family: Tahoma, Geneva, sans-serif;
                        color: #ccfbca;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .fancy_text {
                        font-family: Arial, Helvetica, sans-serif;
                        color: #75ae43;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .night_text {
                        font-family: Arial, Helvetica, sans-serif;
                        color: #bd73b0;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .sky_text {
                        font-family: Arial, Helvetica, sans-serif;
                        color: #ac985e;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .multi_text {
                        font-family: Arial, Helvetica, sans-serif;
                        color: #3096c2;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .flower_text {
                        font-family: Arial, Helvetica, sans-serif;
                        color: #a89b38;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .fountain_text {
                        font-family: Arial, Helvetica, sans-serif;
                        color: #9b5c56;
                        font-size: 18px;
                        font-weight: 700;
                        position: relative;
                        text-align: center;
                        top: 1em;
                    }

                    .topcode_text {
                        font-family: Tahoma, Geneva, sans-serif;
                        color: #000;
                        font-size: 16px;
                        font-weight: 700;
                        position: relative;
                        padding-top: 1em;
                        padding-left: 1.5em;
                    }

                    .powerpoint_text {
                        font-family: Tahoma, Geneva, sans-serif;
                        color: #000;
                        font-size: 16px;
                        font-weight: 700;
                        position: relative;
                        padding-top: 1em;
                        padding-right: 1em;
                    }

                    .kids_buddy {
                        text-align: center;
                        margin-top: 3em
                    }

                    .kids_poppet {
                        text-align: center;
                        bottom: 3em;
                        margin-top: 3em;
                        padding-bottom: 3em;
                    }

                    .fancy_ispin {
                        text-align: center;
                        margin-top: 5em
                    }

                    .fancy_hot {
                        text-align: center;
                        margin-top: 7em;
                        padding-bottom: 2em;
                    }

                    .multi_boom {
                        text-align: center;
                        margin-top: 7em
                    }

                    .multi_lazer {
                        text-align: center;
                        margin-top: 8em;
                        padding-bottom: 3em;
                    }

                    .night_world {
                        text-align: center;
                        margin-top: 2em
                    }

                    .sky_star {
                        text-align: center;
                        margin-top: 0em;
                        margin-bottom: 1em;
                    }

                    .sky_gel {
                        text-align: center;
                        margin-top: 5em;
                        padding-bottom: 2.8em;
                    }

                    .title_bg {
                        border: 1px solid #fb9a28;
                        padding: 5px 20px;
                        background-image: linear-gradient(#fff, #fff);
                        width: 35%;
                        margin: 17px auto;
                    }

                    .p-b-50 {
                        text-align: center;
                    }

                    .categoreies_list {
                        display: none !important;
                    }

                }

                .color1:hover {
                    color: red;
                }

                .main_contain {
                    position: relative;
                    width: 80%;
                    margin: 0 auto;
                }

                .column {
                    float: left;
                    width: 33%;
                    padding: 5px;
                    margin: 0px;
                    border-radius: 6px;
                    height: auto;
                    /* Should be removed. Only for demonstration */
                }

                .column1 {
                    float: left;
                    width: 65.4%;
                    padding: 3px;
                    margin: 0px;
                    border-radius: 6px;
                    height: auto;
                    /* Should be removed. Only for demonstration */
                }

                .column2 {
                    float: left;
                    width: 98.5%;
                    padding: 3px;
                    margin: 10px;
                    border-radius: 6px;
                    height: auto;
                    /* Should be removed. Only for demonstration */
                }

                .row:after {
                    content: "";
                    display: table;
                    clear: both;
                }

                .d_img {
                    width: 100%;
                    border-radius: 6px;
                }

                .exzoom {
                    box-sizing: border-box;
                }

                .exzoom * {
                    box-sizing: border-box;
                }

                .exzoom .exzoom_img_box {
                    background: #eee;
                    position: relative;
                }

                .exzoom .exzoom_img_box .exzoom_main_img {
                    display: block;
                    width: 100%;
                }

                .exzoom .exzoom_img_box span {
                    background: url("data:img/jpg;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAACXBIWXMAAAsTAAALEwEAmpwYAAAK\aTWlDQ1BQaG90b3Nob3AgSUNDIHByb2ZpbGUAAHjanVN3WJP3Fj7f92UPVkLY8LGXbIEAIiOsCMgQ\aWaIQkgBhhBASQMWFiApWFBURnEhVxILVCkidiOKgKLhnQYqIWotVXDjuH9yntX167+3t+9f7vOec\a 5/zOec8PgBESJpHmomoAOVKFPDrYH49PSMTJvYACFUjgBCAQ5svCZwXFAADwA3l4fnSwP/wBr28A\a AgBw1S4kEsfh/4O6UCZXACCRAOAiEucLAZBSAMguVMgUAMgYALBTs2QKAJQAAGx5fEIiAKoNAOz0\aST4FANipk9wXANiiHKkIAI0BAJkoRyQCQLsAYFWBUiwCwMIAoKxAIi4EwK4BgFm2MkcCgL0FAHaO\aWJAPQGAAgJlCLMwAIDgCAEMeE80DIEwDoDDSv+CpX3CFuEgBAMDLlc2XS9IzFLiV0Bp38vDg4iHi\awmyxQmEXKRBmCeQinJebIxNI5wNMzgwAABr50cH+OD+Q5+bk4eZm52zv9MWi/mvwbyI+IfHf/ryM\a AgQAEE7P79pf5eXWA3DHAbB1v2upWwDaVgBo3/ldM9sJoFoK0Hr5i3k4/EAenqFQyDwdHAoLC+0l\aYqG9MOOLPv8z4W/gi372/EAe/tt68ABxmkCZrcCjg/1xYW52rlKO58sEQjFu9+cj/seFf/2OKdHi\aNLFcLBWK8ViJuFAiTcd5uVKRRCHJleIS6X8y8R+W/QmTdw0ArIZPwE62B7XLbMB+7gECiw5Y0nYA\aQH7zLYwaC5EAEGc0Mnn3AACTv/mPQCsBAM2XpOMAALzoGFyolBdMxggAAESggSqwQQcMwRSswA6c\awR28wBcCYQZEQAwkwDwQQgbkgBwKoRiWQRlUwDrYBLWwAxqgEZrhELTBMTgN5+ASXIHrcBcGYBie\awhi8hgkEQcgIE2EhOogRYo7YIs4IF5mOBCJhSDSSgKQg6YgUUSLFyHKkAqlCapFdSCPyLXIUOY1c\aQPqQ28ggMor8irxHMZSBslED1AJ1QLmoHxqKxqBz0XQ0D12AlqJr0Rq0Hj2AtqKn0UvodXQAfYqO\aY4DRMQ5mjNlhXIyHRWCJWBomxxZj5Vg1Vo81Yx1YN3YVG8CeYe8IJAKLgBPsCF6EEMJsgpCQR1hM\aWEOoJewjtBK6CFcJg4Qxwicik6hPtCV6EvnEeGI6sZBYRqwm7iEeIZ4lXicOE1+TSCQOyZLkTgoh\aJZAySQtJa0jbSC2kU6Q+0hBpnEwm65Btyd7kCLKArCCXkbeQD5BPkvvJw+S3FDrFiOJMCaIkUqSU\a Eko1ZT/lBKWfMkKZoKpRzame1AiqiDqfWkltoHZQL1OHqRM0dZolzZsWQ8ukLaPV0JppZ2n3aC/p\a dLoJ3YMeRZfQl9Jr6Afp5+mD9HcMDYYNg8dIYigZaxl7GacYtxkvmUymBdOXmchUMNcyG5lnmA+Y\a b1VYKvYqfBWRyhKVOpVWlX6V56pUVXNVP9V5qgtUq1UPq15WfaZGVbNQ46kJ1Bar1akdVbupNq7O\aUndSj1DPUV+jvl/9gvpjDbKGhUaghkijVGO3xhmNIRbGMmXxWELWclYD6yxrmE1iW7L57Ex2Bfsb\a di97TFNDc6pmrGaRZp3mcc0BDsax4PA52ZxKziHODc57LQMtPy2x1mqtZq1+rTfaetq+2mLtcu0W\a 7eva73VwnUCdLJ31Om0693UJuja6UbqFutt1z+o+02PreekJ9cr1Dund0Uf1bfSj9Rfq79bv0R83\aMDQINpAZbDE4Y/DMkGPoa5hpuNHwhOGoEctoupHEaKPRSaMnuCbuh2fjNXgXPmasbxxirDTeZdxr\aPGFiaTLbpMSkxeS+Kc2Ua5pmutG003TMzMgs3KzYrMnsjjnVnGueYb7ZvNv8jYWlRZzFSos2i8eW\a 2pZ8ywWWTZb3rJhWPlZ5VvVW16xJ1lzrLOtt1ldsUBtXmwybOpvLtqitm63Edptt3xTiFI8p0in1\aU27aMez87ArsmuwG7Tn2YfYl9m32zx3MHBId1jt0O3xydHXMdmxwvOuk4TTDqcSpw+lXZxtnoXOd\a 8zUXpkuQyxKXdpcXU22niqdun3rLleUa7rrStdP1o5u7m9yt2W3U3cw9xX2r+00umxvJXcM970H0\a 8PdY4nHM452nm6fC85DnL152Xlle+70eT7OcJp7WMG3I28Rb4L3Le2A6Pj1l+s7pAz7GPgKfep+H\avqa+It89viN+1n6Zfgf8nvs7+sv9j/i/4XnyFvFOBWABwQHlAb2BGoGzA2sDHwSZBKUHNQWNBbsG\aLww+FUIMCQ1ZH3KTb8AX8hv5YzPcZyya0RXKCJ0VWhv6MMwmTB7WEY6GzwjfEH5vpvlM6cy2CIjg\aR2yIuB9pGZkX+X0UKSoyqi7qUbRTdHF09yzWrORZ+2e9jvGPqYy5O9tqtnJ2Z6xqbFJsY+ybuIC4\aqriBeIf4RfGXEnQTJAntieTE2MQ9ieNzAudsmjOc5JpUlnRjruXcorkX5unOy553PFk1WZB8OIWY\a EpeyP+WDIEJQLxhP5aduTR0T8oSbhU9FvqKNolGxt7hKPJLmnVaV9jjdO31D+miGT0Z1xjMJT1Ir\a eZEZkrkj801WRNberM/ZcdktOZSclJyjUg1plrQr1zC3KLdPZisrkw3keeZtyhuTh8r35CP5c/Pb\a FWyFTNGjtFKuUA4WTC+oK3hbGFt4uEi9SFrUM99m/ur5IwuCFny9kLBQuLCz2Lh4WfHgIr9FuxYj\ai1MXdy4xXVK6ZHhp8NJ9y2jLspb9UOJYUlXyannc8o5Sg9KlpUMrglc0lamUycturvRauWMVYZVk\aVe9ql9VbVn8qF5VfrHCsqK74sEa45uJXTl/VfPV5bdra3kq3yu3rSOuk626s91m/r0q9akHV0Ibw\a Da0b8Y3lG19tSt50oXpq9Y7NtM3KzQM1YTXtW8y2rNvyoTaj9nqdf13LVv2tq7e+2Sba1r/dd3vz\a DoMdFTve75TsvLUreFdrvUV99W7S7oLdjxpiG7q/5n7duEd3T8Wej3ulewf2Re/ranRvbNyvv7+y\a CW1SNo0eSDpw5ZuAb9qb7Zp3tXBaKg7CQeXBJ9+mfHvjUOihzsPcw83fmX+39QjrSHkr0jq/dawt\ao22gPaG97+iMo50dXh1Hvrf/fu8x42N1xzWPV56gnSg98fnkgpPjp2Snnp1OPz3Umdx590z8mWtd\aUV29Z0PPnj8XdO5Mt1/3yfPe549d8Lxw9CL3Ytslt0utPa49R35w/eFIr1tv62X3y+1XPK509E3r\aO9Hv03/6asDVc9f41y5dn3m978bsG7duJt0cuCW69fh29u0XdwruTNxdeo94r/y+2v3qB/oP6n+0\a/rFlwG3g+GDAYM/DWQ/vDgmHnv6U/9OH4dJHzEfVI0YjjY+dHx8bDRq98mTOk+GnsqcTz8p+Vv95\a 63Or59/94vtLz1j82PAL+YvPv655qfNy76uprzrHI8cfvM55PfGm/K3O233vuO+638e9H5ko/ED+\aUPPR+mPHp9BP9z7nfP78L/eE8/sl0p8zAAAAIGNIUk0AAHolAACAgwAA+f8AAIDpAAB1MAAA6mAA\a ADqYAAAXb5JfxUYAAAAcSURBVHjaYnz9+Vs5AxJgYkADhAUAAAAA//8DANmxA1Okl3sAAAAAAElF\aTkSuQmCC") repeat;
                }

                .exzoom .exzoom_preview {
                    margin: 0;
                    position: absolute;
                    top: 0;
                    overflow: hidden;
                    z-index: 999;
                    background-color: #fff;
                    border: 1px solid #ddd;
                    display: none;
                }

                .exzoom .exzoom_preview .exzoom_preview_img {
                    position: relative;
                    max-width: initial !important;
                    max-height: initial !important;
                    left: 0;
                    top: 0;
                }

                .exzoom .exzoom_nav {
                    margin-top: 10px;
                    overflow: hidden;
                    position: relative;
                    left: 15px;
                }

                .exzoom .exzoom_nav .exzoom_nav_inner {
                    position: absolute;
                    left: 0;
                    top: 0;
                    margin: 0;
                }

                .exzoom .exzoom_nav .exzoom_nav_inner span {
                    border: 1px solid #ddd;
                    overflow: hidden;
                    position: relative;
                    float: left;
                }

                .exzoom .exzoom_nav .exzoom_nav_inner span.current {
                    border: 1px solid #f60;
                }

                .exzoom .exzoom_nav .exzoom_nav_inner span img {
                    max-width: 100%;
                    max-height: 100%;
                    position: relative;
                }

                .exzoom .exzoom_btn {
                    position: relative;
                    margin: 0;
                }

                .exzoom .exzoom_btn a {
                    display: block;
                    width: 15px;
                    border: 1px solid #ddd;
                    height: 60px;
                    line-height: 60px;
                    background: #eee;
                    text-align: center;
                    font-size: 18px;
                    position: absolute;
                    left: 0;
                    top: -62px;
                    text-decoration: none;
                    color: #999;
                }

                .exzoom .exzoom_btn a:hover {
                    background: #f60;
                    color: #fff;
                }

                .exzoom .exzoom_btn a.exzoom_next_btn {
                    left: auto;
                    right: 0;
                }

                .exzoom .exzoom_zoom {
                    position: absolute;
                    left: 0;
                    top: 0;
                    display: none;
                    z-index: 5;
                    cursor: pointer;
                }

                @media screen and (max-width: 768px) {
                    .exzoom .exzoom_zoom_outer {
                        display: none;
                    }
                }

                .exzoom .exzoom_img_ul_outer {
                    border: 1px solid #ddd;
                    position: absolute;
                    overflow: hidden;
                }

                .exzoom .exzoom_img_ul_outer .exzoom_img_ul {
                    padding: 0;
                    margin: 0;
                    overflow: hidden;
                    position: absolute;
                }

                .exzoom .exzoom_img_ul_outer .exzoom_img_ul li {
                    list-style: none;
                    display: inline-block;
                    text-align: center;
                    float: left;
                }

                .exzoom .exzoom_img_ul_outer .exzoom_img_ul li img {
                    width: 100%;
                }
    </style>
    <style>

        body{
            font-family: Arial;
            padding: 30px;
            background: #f5f5f5;
        }

        .main-image{
            width: 600px;
            height: 400px;
            overflow: hidden;
            border: 3px solid #333;
            border-radius: 12px;
            margin-bottom: 15px;
            background: #fff;
        }

        .main-image img{
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.4s ease;
        }

        .thumb-images{
            display: flex;
            gap: 12px;
        }

        .thumb{
            width: 100px;
            height: 100px;
            object-fit: cover;
            cursor: pointer;

            border: 3px solid transparent;
            border-radius: 10px;

            transition: all 0.3s ease;
        }

        .thumb:hover{
            transform: scale(1.08);
            border: 3px solid #ff4d4d;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.3);
        }

        .active-thumb{
            border: 3px solid #ff0000 !important;
        }

        /* Video Button */
        .video-btn{
            margin-top: 20px;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            background: red;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .video-btn:hover{
            transform: scale(1.05);
            background: #cc0000;
        }

        /* Video Popup */
        .video-popup{
            display: none;

            position: fixed;
            left: 0;
            top: 0;

            width: 100%;
            height: 100%;

            background: rgba(0,0,0,0.8);

            justify-content: center;
            align-items: center;

            z-index: 9999;
        }

        .video-box{
            position: relative;
            width: 80%;
            max-width: 800px;
        }

        .video-box iframe{
            width: 100%;
            height: 450px;
            border-radius: 12px;
        }

        .close-btn{
            position: absolute;
            top: -40px;
            right: 0;
            color: #fff;
            font-size: 30px;
            cursor: pointer;
        }

        /* Video Popup */
        .video-popup{
            display: none;

            position: fixed;
            left: 0;
            top: 0;

            width: 100%;
            height: 100%;

            background: rgba(0,0,0,0.85);

            z-index: 9999;

            /* CENTER */
            justify-content: center;
            align-items: center;
        }

        /* Active Popup */
        .video-popup.show{
            display: flex;
        }

        /* Video Box */
        .video-box{
            position: relative;

            width: 80%;
            max-width: 850px;

            background: #000;

            border-radius: 15px;

            overflow: hidden;

            animation: popupZoom 0.3s ease;
        }

        /* Youtube iframe */
        .video-box iframe{
            width: 100%;
            height: 480px;
            display: block;
        }

        /* Close Button */
        .close-btn{
            position: absolute;

            top: 10px;
            right: 15px;

            width: 40px;
            height: 40px;

            background: red;
            color: #fff;

            border-radius: 50%;

            text-align: center;
            line-height: 40px;

            font-size: 24px;
            font-weight: bold;

            cursor: pointer;

            z-index: 10000;

            transition: 0.3s;
        }

        .close-btn:hover{
            transform: rotate(90deg) scale(1.1);
            background: #ff0000;
        }

        /* Popup Animation */
        @keyframes popupZoom{

            from{
                transform: scale(0.7);
                opacity: 0;
            }

            to{
                transform: scale(1);
                opacity: 1;
            }

        }

    </style>
    </head>
    <body>
        <div class="row">

            <div class="exzoom p-t-50 col-sm-4" id="exzoom">
                <!-- Images -->
                <div class="exzoom_img_box" style="width: 441px; height: 441px;">
                    <div class="exzoom_img_ul_outer" style="width: 441px; height: 441px;">
                        <ul class="exzoom_img_ul" style="width: 882px; left: -441px;">

                            <li style="width: 441px;"><img src="<?= ASSETS_URL . '/img/trademark.png'?>" style="margin-top: 31.899px; width: 441px;"> </li>
                            <li style="width: 441px;"><img src="<?= ASSETS_URL . '/img/logo.png'?>" style="margin-top: 31.899px; width: 441px;"> </li>
                        </ul>
                    </div>
                    <div class="exzoom_zoom_outer" style="width: 441px; height: 377.202px; top: 31.899px; left: 0px; position: relative;">
                        <span class="exzoom_zoom" style="width: 188.601px; height: 188.601px; display: none; left: 220.351px; top: 0px;"></span>
                    </div>
                    <p class="exzoom_preview" style="width: 441px; height: 441px; left: 446px; display: none;">
                        <img class="exzoom_preview_img" src="<?= ASSETS_URL . '/img/logo.png'?>" style="width: 1031.18px; height: 882px; left: -512.923px; top: 0px;">
                    </p>
                </div>
                <div class="exzoom_nav" style="height: 62px;">
                    <p class="exzoom_nav_inner" style="width: 138px; left: 0px;"><span class="" style="margin-left: 7px; width: 60px; height: 60px;"><img src="<?= ASSETS_URL . '/img/trademark.png'?>" width="60" style="top:4.34px;"></span><span style="margin-left: 7px; width: 60px; height: 60px;" class="current"><img src="<?= ASSETS_URL . '/img/logo.png'?>" width="60" style="top:4.34px;"></span></p>
                </div>

            </div>
            <div class="col-sm-8 p-t-50 padding-left">
                <h4 class="product-detail-name m-text16 p-b-13 padding-left" style="font-weight: bold;">
                    Trixx </h4>


                <div class="p-t-10">
                    <h6 class="product-detail-name col-sm-2 col-md-3 col-lg-2 p-b-13 padding-left" style="float: left;color: #afadad; font-weight: bold;">Function:</h6>
                    <div class="product-detail-name col-sm-10 col-md-9 col-lg-10  p-b-13 padding-left" style="float: left;">
                        <ul class="des_text">
                            <li>Shoots up with a pink tail and blasts.</li>
                        </ul>
                    </div>
                </div>

                <div class="p-t-10">
                    <h6 class="product-detail-name col-sm-2 col-md-3 col-lg-2 p-b-13 padding-left" style="float: left;color: #afadad; font-weight: bold;">Box:</h6>
                    <div class="product-detail-name col-sm-10 col-md-9 col-lg-10  p-b-13 padding-left" style="float: left;">
                        <ul class="des_text">
                            <li>4 Pieces</li>
                        </ul>
                    </div>
                </div>

                <div class="p-t-10">
                    <h6 class="product-detail-name col-sm-2 col-md-3 col-lg-2 p-b-13 padding-left" style="float: left;color: #afadad; font-weight: bold;">Carton:</h6>
                    <div class="product-detail-name col-sm-10 col-md-9 col-lg-10  p-b-13 padding-left" style="float: left;">
                        <ul class="des_text">
                            <li>160 pkts</li>
                        </ul>
                    </div>
                </div>

                <!--  <div class="p-t-10"> 
                                        <h6 class="product-detail-name col-sm-2 col-md-3 col-lg-2  p-b-13 padding-left" style="float: left;color: #afadad; font-weight: bold;">Product Description:</h6>
                                        <div class="product-detail-name col-sm-10 col-md-9  col-lg-10 p-b-13 padding-left" style="float: left;">
                                        <p class="des_text">Stay connected to the world and get fit in style with the Apple Watch Series 4. Strap on the stylish Apple Watch and listen to music, view photos and messages, track your fitness levels and more. Featuring a stylish design, this Apple watch is your ideal partner when it comes to work or workouts.</p>
                                        </div>
                                        </div>
                                        -->
                <div class="p-t-10">
                    <h6 class="product-detail-name col-sm-2 col-md-3 col-lg-2  p-b-13 padding-left" style="float: left;color: #afadad; font-weight: bold;">Watch Video:</h6>
                    <a class="product-detail-name col-sm-10 col-md-9 col-lg-10  p-b-13 padding-left des_text" style="float: left;" href="https://www.youtube.com/embed/dG3hFtMUZ24" target="_blank">
                        <span><i class="fa fa-youtube-play" style="font-size:px;color:red"></i></span><span style="font-weight: bold;"> &nbsp;Click Here !</span>
                    </a>
                </div>

            </div>




        </div>

        <div class="gallery-container">

    <!-- Main Image -->
    <div class="main-image">
        <img id="mainProductImage"
             src="https://picsum.photos/id/1015/600/400">
    </div>

    <!-- Thumbnails -->
    <div class="thumb-images">

        <img class="thumb active-thumb"
             src="https://picsum.photos/id/1015/100/100">

        <img class="thumb"
             src="https://picsum.photos/id/1025/100/100">

        <img class="thumb"
             src="https://picsum.photos/id/1035/100/100">

        <img class="thumb"
             src="https://picsum.photos/id/1045/100/100">

    </div>

    <!-- Video Button -->
    <button class="video-btn">
        ▶ Watch Product Video
    </button>

</div>

<!-- Video Popup -->
<div class="video-popup">

    <div class="video-box">

        <span class="close-btn">&times;</span>

        <iframe id="youtubeVideo"
            src=""
            frameborder="0"
            allowfullscreen>
        </iframe>

    </div>

</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>

$(document).ready(function(){

    /* Image Hover Change */

    $(".thumb").hover(function(){

        var thumbSrc = $(this).attr("src");

        var bigImg = thumbSrc.replace("/100/100", "/600/400");

        $("#mainProductImage").fadeOut(200,function(){

            $(this)
                .attr("src", bigImg)
                .fadeIn(300);

        });

        $(".thumb").removeClass("active-thumb");

        $(this).addClass("active-thumb");

    });

    /* Open Video Popup */

    $(".video-btn").click(function(){

        $(".video-popup").fadeIn();

        $("#youtubeVideo").attr(
            "src",
            "https://www.youtube.com/embed/jTtdgacByM8?autoplay=1"
        );

    });

    /* Close Video Popup */

    $(".close-btn, .video-popup").click(function(){

        $(".video-popup").fadeOut();

        $("#youtubeVideo").attr("src","");

    });

    $(".video-box").click(function(e){
        e.stopPropagation();
    });

    /* Open Popup */
    $(".video-btn").click(function(){

        $(".video-popup").addClass("show");

        $("#youtubeVideo").attr(
            "src",
            "https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1"
        );

    });

    /* Close Popup */
    $(".close-btn, .video-popup").click(function(){

        $(".video-popup").removeClass("show");

        $("#youtubeVideo").attr("src","");

    });

    /* Prevent Close when clicking inside video box */
    $(".video-box").click(function(e){

        e.stopPropagation();

    });

});

</script>

</html>