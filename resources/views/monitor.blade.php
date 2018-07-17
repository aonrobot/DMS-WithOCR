<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title></title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous">
        <link rel="stylesheet" href="{{asset('vendor/jstree/dist/themes/default/style.min.css')}}" />
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
        <style>
            
        </style>
    </head>
    <body>
        <!--[if lt IE 7]>
            <p class="browsehappy">You are using an <strong>outdated</strong> browser. Please <a href="#">upgrade your browser</a> to improve your experience.</p>
        <![endif]-->
        <div class="container">
            <div class="row mt-5">
                <div class="col-md-12">
                    <h2>File Watcher Status : <small class="text-success">Online</small></h2>
                    <hr>
                </div>
                <div class="col-md-12">
                    <h2>List File <h6>Press <code>ESC</code> to hide all content</h6></h2>
                    <br>
                    <div id="jstree_demo_div"></div>
                </div>
            </div>
        </div>
        <input type="hidden" id="fileId">
        <div class="modal fade" id="showContentModal" tabindex="-1" role="dialog" aria-labelledby="showContentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="showContentModalLabel">Content </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <a id="linkRaw" target="_blank"><h4>ไฟล์ต้นฉบับ</h4></a>
                        <hr>
                        <textarea name="content" id="contentTextArea" style="min-width: 100%;min-height: 500px;"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js" integrity="sha384-smHYKdLADwkXOn1EmN1qk/HfnUcbVRZyYmZ4qpPea6sjB/pTJ0euyQp0Mk8ck+5T" crossorigin="anonymous"></script>
        <script src="{{asset('vendor/jstree/dist/jstree.min.js')}}"></script>
        <script>
            $('.collapseBtn').click(function(){
                $('.collapse').collapse('hide')
            });

            $(document).keyup(function(event){
                if(event.keyCode === 27){
                    $('.collapse').collapse('hide')
                }
            })
            
            $(function () { 
                $('#showContentModal').on('show.bs.modal', function (e) {
                    var fileId = $('#fileId').val();
                    var modal = $(this);
                    $.get('api/file/content/' + fileId).done(function(result){
                        console.log(result);
                        modal.find('#linkRaw').prop('href', 'api/showFile/' + fileId);
                        modal.find('#contentTextArea').text(result);
                    });
                });

                $('#jstree_demo_div').on('changed.jstree', function (e, data) {
                    $('#fileId').val(data.selected[0]);
                    if(data.selected[0].indexOf('\\') == -1){
                        $('#showContentModal').modal('show');

                    }
                }).jstree({
                    'core' : {
                        'data' : {
                            'url' : function (node) {
                                console.log(node.id);
                                return node.id === '#' ? 'api/file/tree/json/root' : 'api/file/tree/json/child/' + btoa(node.id);
                            },
                            'data' : function (node) {
                                return { 'id' : node.id };
                            }
                        }
                    }
                }); 
            });
        </script>
    </body>
</html>
