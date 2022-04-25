/* WEBSITE FOR DOCS, SCRIPT AND PLUGIN CREATED BY Diego H. O. R. Antunes */

window.onload = () => {
    var sx = setInterval(() => {
        if($("#billing_first_name").val() == "" || $("#billing_last_name").val() == "" || $("#billing_address_1").val() == "" || $("#billing_city").val() == "" || $("#billing_postcode").val() == "" || $("#billing_phone").val() == "" || $("#billing_email").val() == ""){
            $("[a1]").css("display", "none");
            $("[a2]").css("display", "flex");
            $("[a2]").text("Preencha todos os campos necessários para realizar o pagamento.")
        }else{
            if(isNaN($("#billing_postcode").val().replaceAll("-", "")) || isNaN($("#billing_phone").val())){
                $("[a2]").text("Preencha os corretamente os campos para o telefone e o código postal.")
            }else{
                $("[a1]").css("display", "flex");
                $("[a2]").css("display", "none");
                clearInterval(sx)
            }
        }
    }, 500)
}

function verificar(dd){
    //variavel com temporizados
    var s = setInterval(()=> {
        Waves.API.Node.v1.assets.balance(address, dd.id).then((balance) => {
            console.log(balance);
            var b = balance.balance;
            var v = balance.balance == 0 ? 0 : parseFloat(balance.balance / decimals)
            console.log('v: ' + v)
            if(v == $('#valor').text()){
                Waves.API.Node.v1.transactions.getList(address).then((data) => {
                    var transferData = {

                        // An arbitrary address; mine, in this example
                        recipient: addsss,
                    
                        // ID of a token, or WAVES
                        assetId: dd.id,
                        
                        // The real amount is the given number divided by 10^(precision of the token)
                        amount: parseInt(b - (dd.fee * decimals)),
                    
                        // The same rules for these two fields
                        feeAssetId: dd.id,
                        fee: parseInt(dd.fee * decimals),
                    
                        // 140 bytes of data (it's allowed to use Uint8Array here) 
                        attachment: 'Pagamento por compra de produto. TX:' + data[0].id,
                        
                        timestamp: Date.now()
                    
                    };
                    console.log(transferData)
                    Waves.API.Node.v1.assets.transfer(transferData, seed.keyPair).then(async (responseData) => {
                        clearInterval(s)
                        document.cookie = 'tx=' + await responseData.id
                        $('#contador').attr('class','contad');
                        $('.contad').attr('id','')
                        $('.contad').text(`Recebido com sucesso. Clique em 'Finalizar Compra'.`);
                        Swal.fire(
                            'Sucesso',
                            'Pagamento no valor de ' + parseFloat(data[0].amount / decimals) + ' ' + dd.name + ' foi recebido com sucesso!',
                            'success'
                        ).then(async (result) => {
                            document.cookie = 'tx='  + await responseData.id;
                            var botoes = document.getElementsByTagName('button');
                            for (var i = 0; i < botoes.length; i++) {
                                $(dd.query).click();
                            }
                        })
                    });
                })
            }else if(v != 0 || v > $('#valor').text()){
                $('.contad').html('Valor em falta, ficou faltando ' + parseFloat($('#valor').text() - v) + ' para completar o valor. Não saia da página e não envie mais nenhum valor, estamos te reembolsando o valor enviado.' );
                $('#contador').attr('class','contad');
                $('.contad').attr('id','')
                Waves.API.Node.v1.transactions.getList(address).then((data) => {
                    var transferData = {

                        // An arbitrary address; mine, in this example
                        recipient: data[0].sender,
                    
                        // ID of a token, or WAVES
                        assetId: dd.id,
                        
                        // The real amount is the given number divided by 10^(precision of the token)
                        amount: parseInt(b - (dd.fee * decimals)),
                    
                        // The same rules for these two fields
                        feeAssetId: dd.id,
                        fee: parseInt(dd.fee * decimals),
                    
                        // 140 bytes of data (it's allowed to use Uint8Array here) 
                        attachment: 'Devolução de valores por falta no pagamento. TX:' + data[0].id,
                        
                        timestamp: Date.now()
                    
                    };
                    console.log(transferData)
                    Waves.API.Node.v1.assets.transfer(transferData, seed.keyPair).then((responseData) => {
                        clearInterval(s)
                        Swal.fire(
                            'Devolução de valores',
                            'Detectamos que foi enviado um valor menor/maior que o solicitado para o pagamento, o valor de ' + parseFloat(data[0].amount / decimals) + ' ' + dd.name + ' foi devolvido para o endereço ao qual o enviou. Tente realizar novamente o pagamento com o valor correto.',
                            'warning'
                        ).then((result) => {
                            document.location.reload(true)
                        })
                    });
                })
            }
        })
    }, 1000)
}

/* formatar marketdata a ser utilizado */
function marketdata(k,j,i){
    if(k == "mainnet"){
        return 'https://matcher.waves.exchange/matcher/orderbook/' + j + '/' + i + '#getOrderBook'
    }else{
        return 'https://matcher-testnet.waves.exchange/matcher/orderbook/' + j + '/' + i + '#getOrderBook'
    }
}

/* obtem o preço do ativo na moeda fiduciaria escolhida para as configurações iniciais e define valores */
function pricepay(dda){
    var urll = 'https://api.coingecko.com/api/v3/simple/price?ids=neutrino&vs_currencies=' + fiduciaria
    var url = 'https://api.coingecko.com/api/v3/simple/price?ids=WAVES&vs_currencies=' + fiduciaria
    console.log(dda)
    if(dda.id == "WAVES" || dda == null || dda == undefined){
        getJSON(url, (err,data) => {
            let pd = parseFloat(topay/data.waves[fiduciaria.toLowerCase()])
            $('#valor').text(pd.toFixed(dda.decimals))
        })
    }else if(dda.id == "DG2xFkPdDwKUoBkzGAhQtLpSGzfXLiCYPEzeKH2Ad24p" || dda.id == "25FEqEjRkqK6yCkiT7Lz6SAYz7gUFCtxfCChnrVFD5AT"){
        getJSON(urll, (err,data) => {
            let pd = parseFloat(topay/data.neutrino[fiduciaria.toLowerCase()])
            $('#valor').text(pd.toFixed(dda.decimals))
        })
    }else{
        var net = address.substr(0,2) != "3P" ? "testnet" : "mainnet";
        var usdnn = net == "testnet" ? "25FEqEjRkqK6yCkiT7Lz6SAYz7gUFCtxfCChnrVFD5AT" : "DG2xFkPdDwKUoBkzGAhQtLpSGzfXLiCYPEzeKH2Ad24p"
        $.ajax({
            url: marketdata(net, usdnn, dda.id),
            type: 'GET',
            success: (data) => {
                console.log(data)
                var val = parseFloat(data.bids[0].price / (10**6));
                getJSON(urll, (err,data) => {
                    console.log(data.neutrino[fiduciaria.toLowerCase()])
                    let pd = parseFloat(topay / (val*data.neutrino[fiduciaria.toLowerCase()]))
                    $('#valor').text(pd.toFixed(dda.decimals))
                })
            },
            error: (data) => {
                $.ajax({
                    url: marketdata(net, usdnn, dda.id),
                    type: 'GET',
                    success: (data) => {
                        console.log(data)
                        var val = parseFloat(data.bids[0].price / (10**6));
                        getJSON(urll, (err,data) => {
                            console.log(data.neutrino[fiduciaria.toLowerCase()])
                            let pd = parseFloat(topay / (val*data.neutrino[fiduciaria.toLowerCase()]))
                            $('#valor').text(pd.toFixed(dda.decimals))
                        })
                    },
                    error: (data) => {
                        console.log(data)
                    }
                })
            }
        })
    }
}

/* Define as configurações iniciais para o token selecionado como forma de pagamento */
function muddd(el){
    //obter detalhes do asset presente na seleção do mesmo
    var dd = JSON.parse(el.value);

    //definir casas decimais do asset escolhido
    das(dd.decimals);

    //json para informações para a função pricepay
    var ddd = {
        decimals: dd.decimals,
        id: dd.id,
        m: dd.matcher,

    }
    //define o preço do token com base nos dados acima
    pricepay(ddd);
    
    //json para informações para a função verificar
    var ddc = {
        id: dd.id,
        address: addsss,
        fee: dd.fee,
        name: dd.name,
        query: dd.query
    };
    //inicia verificação de depósito e confirmação de valores com base nos dados acimas
    verificar(ddc)
}

/* definir casas decimais de um asset */
function das(d){
    decimals = parseInt(10 ** d);
}

/* Adição de seleção de Asset para forma de pagamento */
function contSelect(d,n){
    var nn = 0;
    while(nn<=(n-1)){
        $("#contSelect").append("<option style='border-radius: 10px; border: 1px solid #e0e0e0; box-shadow: -2px 2px 2px 0px #e0e0e0; background: #fff; padding: 10px; color: #333;' onclick='muddd(this)' onselect='muddd(this)' value='" + JSON.stringify(d[nn]) + "'>" + d[nn].name + "</option>");
        nn++;
    }
}

/* Função de copiar para a área de trasnferencia */
function copy(r, t){
    navigator.clipboard.writeText($(r).text());
    Swal.fire(
        'Endereço copiado',
        'Endereço de depósito foi copiado com sucesso para a área de transferência.',
        'success'
    ).then((re) => {
        if(t == 't'){
            document.location.reload(true)
            navigator.clipboard.writeText(address)
        }
})}

/* Iniciar timer contagem regressiva */
function startTimer(duration) {
    var timer = duration, minutes, seconds;
    var s = setInterval(function () {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        document.getElementById('contador').textContent = minutes + ':' + seconds;
        if (--timer < 0) {
            Swal.fire({
                title:'Tempo esgotado!',
                text:'Tempo para depósito de fundos esgotado, refaça novamente o procedimento de pagamento com ' + dd.name + '. Caso já tenha feito o pagamento, entre em contato com nosso suporte e foneça o endereço ' + address + '.',
                icon:'warning',
                showCancelButton: true,
                cancelButtonText: 'Copiar endereço',
                confirmButtonText: 'Voltar'
            }).then((result) => {
                if(result.isDismissed){
                    copy(address, 't')
                }else{
                document.location.reload(true)
                }
            })
            clearInterval(s)
        }
    }, 1000);
}

/* Timer da página de compra */
setInterval(() => {
    $('#imga').css('height',document.getElementById('imga').clientWidth)
},500)


/* solicitação javascript em json */
var getJSON = function(url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.responseType = 'json';
    xhr.onload = function() {
      var status = xhr.status;
      if (status === 200) {
        callback(null, xhr.response);
      } else {
        callback(status, xhr.response);
      }
    };
    xhr.send();
};