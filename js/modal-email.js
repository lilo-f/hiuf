document.addEventListener('DOMContentLoaded', function() {
    let selectedDiscountValue = null;

    const discountModal = document.getElementById('discount-modal');
    const giftSelection = document.getElementById('gift-selection');
    const scratchGame = document.getElementById('scratch-game');
    const discountResult = document.getElementById('discount-result');
    const emailForm = document.getElementById('email-form');
    const emailInput = document.getElementById('email-input');
    const finalDiscountSpan = document.getElementById('final-discount');
    const discountAmountSpan = document.getElementById('discount-amount');
    const modalCloseButton = document.getElementById('modal-close');
    const errorMessage = document.getElementById('email-error');
    const alertContainer = document.getElementById('alert-container'); // Pegue o novo contêiner

    // Função para mostrar os alertas personalizados
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `custom-alert ${type}`;
        alertDiv.textContent = message;

        alertContainer.appendChild(alertDiv);

        // Força o reflow para a animação CSS funcionar
        void alertDiv.offsetWidth;
        alertDiv.classList.add('show');

        // Remove o alerta após 5 segundos
        setTimeout(() => {
            alertDiv.classList.remove('show');
            setTimeout(() => {
                alertDiv.remove();
            }, 500); // Tempo para a animação de saída
        }, 5000);
    }


    // Inicializar o canvas da raspadinha
    const scratchCanvas = document.getElementById('scratch-canvas');
    const ctx = scratchCanvas.getContext('2d');
    let isScratching = false;

    function drawCover() {
        ctx.fillStyle = '#8a2be2'; // Cor de cobertura roxa
        ctx.fillRect(0, 0, scratchCanvas.width, scratchCanvas.height);
        ctx.globalCompositeOperation = 'destination-out';
    }

    function handleScratch(e) {
        if (!isScratching) return;
        
        const rect = scratchCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        ctx.beginPath();
        ctx.arc(x, y, 20, 0, 2 * Math.PI);
        ctx.fill();
        
        // Verificar se a raspadinha está completa
        const pixels = ctx.getImageData(0, 0, scratchCanvas.width, scratchCanvas.height);
        let transparentPixels = 0;
        for (let i = 0; i < pixels.data.length; i += 4) {
            if (pixels.data[i + 3] === 0) {
                transparentPixels++;
            }
        }
        
        const transparencyPercentage = (transparentPixels / (pixels.data.length / 4)) * 100;
        if (transparencyPercentage > 50) { // Se mais de 50% for transparente
            isScratching = false;
            // Exibe o resultado e o formulário
            scratchGame.style.display = 'none';
            discountResult.style.display = 'block';
            discountAmountSpan.textContent = selectedDiscountValue + '%';
            // Restaura o modo de composição do canvas
            ctx.globalCompositeOperation = 'source-over';
        }
    }
    
    // Configurar o canvas
    drawCover();
    
    // Adicionar listeners para o jogo
    scratchCanvas.addEventListener('mousedown', function(e) {
        isScratching = true;
        handleScratch(e);
    });
    scratchCanvas.addEventListener('mousemove', handleScratch);
    scratchCanvas.addEventListener('mouseup', function() {
        isScratching = false;
    });

    // Evento de clique para as opções de presente
    giftSelection.querySelectorAll('.gift-option').forEach(option => {
        option.addEventListener('click', function() {
            selectedDiscountValue = this.dataset.discount;
            finalDiscountSpan.textContent = selectedDiscountValue + '%';
            
            // Transição para a raspadinha
            giftSelection.style.display = 'none';
            scratchGame.style.display = 'block';
            drawCover(); // Resetar o canvas para a nova raspadinha
        });
    });

    // Evento para fechar o modal
    modalCloseButton.addEventListener('click', function() {
        discountModal.style.display = 'none';
        // Resetar o modal para o estado inicial
        giftSelection.style.display = 'flex';
        scratchGame.style.display = 'none';
        discountResult.style.display = 'none';
        drawCover();
    });

    // Envio do formulário
    emailForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const userEmail = emailInput.value;
        
        if (!selectedDiscountValue || !userEmail) {
            errorMessage.textContent = 'Por favor, selecione um cupom e digite um e-mail válido.';
            return;
        }

        const couponCodes = {
            '10': 'BEMVINDO10',
            '15': 'BEMVINDO15',
            '20': 'BEMVINDO20'
        };
        
        const couponCode = couponCodes[selectedDiscountValue];

        try {
            const response = await fetch('../api/send-coupon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: userEmail,
                    coupon: couponCode,
                    discount: selectedDiscountValue
                })
            });
            
            const result = await response.json();
            
            // Lógica para exibir os alertas personalizados
            if (result.success) {
                showAlert('success', result.message);
                modalCloseButton.click();
            } else {
                showAlert('error', result.message);
            }

        } catch (error) {
            showAlert('error', 'Erro ao enviar o e-mail. Tente novamente mais tarde.');
            console.error('Error:', error);
        }
    });
});