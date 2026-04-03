$(function() {
    
    if($('#provider').length){
        
        setProvider();
        $('#provider').change(function(){
            setProvider();
        });
    }
    
    if($('#discount_type').length){
        
        enableDiscount();
        
        $('#discount_enabled').change(function(){
            enableDiscount();
        });
        
        $('#discount_amount_limit').change(function(){
            if(parseFloat($(this).val()) < 0){
                $(this).val(0);
            }
            if(parseFloat($(this).val()) > 100){
                $(this).val(100);
            }
        });
        
        $('#discount_percentage').change(function(){
            if(parseFloat($(this).val()) < 0){
                $(this).val(0);
            }
            if(parseFloat($(this).val()) > 100){
                $(this).val(100);
            }
        });
        
        $('.discount input').keyup(function() {
            $(this).val($(this).val().replace(/[^0-9,]/g,''));
        });
        
        if($('#discount_enabled').prop('checked')){
            setDiscount();
        }
        
        $('#discount_type').change(function(){
            setDiscount();
        });
    }
    
    function setProvider(){
        
        if($('#provider').val() === 'btcpay'){
            console.log('BTCPay');
            $('div.form-group.coinsnap').hide();
            $('div.form-group.coinsnap input[type=text]').removeAttr('required');
            $('div.form-group.btcpay').show();
            $('div.form-group.btcpay input[type=text]').attr('required','required');
        }
        else {
            console.log('Coinsnap');
            $('div.form-group.btcpay').hide();
            $('div.form-group.btcpay input[type=text]').removeAttr('required');
            $('div.form-group.coinsnap').show();
            $('div.form-group.coinsnap input[type=text]').attr('required','required');
        }
    }
    
    function enableDiscount(){
        if($('#discount_enabled').prop('checked')){
            $('.discount').show();
            setDiscount();
        }
        else {
            $('.discount').hide();
        }
    }
    
    function setDiscount(){
        if($('#discount_type').val() === 'fixed'){
            $('.discount.discount-percentage').hide();
            $('.discount.discount-amount').show();
        }
        else {
            $('.discount.discount-amount').hide();
            $('.discount.discount-percentage').show();
        }
    }
});
