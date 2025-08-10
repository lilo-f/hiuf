class UserSession {
    constructor() {
        this.currentUser = JSON.parse(localStorage.getItem('ravenStudioCurrentUser')) || null;
        this.init();
    }
    
    init() {
        this.updateNavbar();
        this.updateGameNavbar();
    }
    
login(userData) {
    const users = JSON.parse(localStorage.getItem('ravenStudioUsers')) || [];
    const storedUser = users.find(u => u.email === userData.email);
    
    // Se encontrou o usuário no localStorage, mescla os dados mantendo o avatar existente
    if (storedUser) {
        userData.avatar = storedUser.avatar || userData.avatar;
        userData.avatarUrl = storedUser.avatarUrl || userData.avatarUrl;
    }
    
    // Completa os dados do usuário
    userData.orders = userData.orders || [];
    userData.wishlist = userData.wishlist || [];
    userData.phone = userData.phone || '';
    userData.joinDate = userData.joinDate || userData.created_at || new Date().toISOString();
    userData.name = userData.name || `${userData.first_name || ''} ${userData.last_name || ''}`.trim();
    
    // Garante que avatarUrl seja a fonte principal
    userData.avatarUrl = userData.avatarUrl || userData.avatar || null;
    userData.avatar = userData.avatarUrl;
    
    this.currentUser = userData;
    localStorage.setItem('ravenStudioCurrentUser', JSON.stringify(userData));
    
    // Atualiza o usuário no array de usuários se existir
    if (storedUser) {
        const userIndex = users.findIndex(u => u.email === userData.email);
        users[userIndex] = {...users[userIndex], ...userData};
        localStorage.setItem('ravenStudioUsers', JSON.stringify(users));
    }
    
    this.updateNavbar();
    return userData;
}

updateAvatar(newAvatarUrl) {
    if (!this.currentUser) {
        console.error("Nenhum usuário logado para atualizar o avatar.");
        return false;
    }

    // Atualiza no localStorage
    this.currentUser.avatar = newAvatarUrl;
    this.currentUser.avatarUrl = newAvatarUrl;
    localStorage.setItem('ravenStudioCurrentUser', JSON.stringify(this.currentUser));

    // Atualiza também no array de usuários globais
    const users = JSON.parse(localStorage.getItem('ravenStudioUsers')) || [];
    const userIndex = users.findIndex(u => u.email === this.currentUser.email);
    if (userIndex !== -1) {
        users[userIndex].avatar = newAvatarUrl;
        users[userIndex].avatarUrl = newAvatarUrl;
        localStorage.setItem('ravenStudioUsers', JSON.stringify(users));
    }

    this.updateNavbar();
    this.updateGameNavbar();
    
    // Força o recarregamento do avatar na página do usuário
    if (typeof loadUserDetails === 'function') {
        loadUserDetails();
    }
    
    return true;
}
    
    isAdmin() {
        return this.currentUser?.isAdmin === true;
    }

async loadAppointments() {
    if (!this.isLoggedIn()) return [];

    try {
        const response = await fetch('http://localhost/trabalhofinal/finalmente/api/appointments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_user_appointments',
                userId: this.currentUser.id
            })
        });

        const data = await response.json();

        if (data.success && data.appointments) {
            const appointments = data.appointments.map(app => ({
                ...app,
                budget: parseFloat(app.budget) || 0
            }));
            
            this.currentUser.appointments = appointments;
            localStorage.setItem('ravenStudioCurrentUser', JSON.stringify(this.currentUser));
            return appointments;
        }
        return [];
    } catch (error) {
        console.error('Erro ao carregar agendamentos:', error);
        return [];
    }
}

    updateGameNavbar() {
        const gameUserLink = document.getElementById('gameUserLink');
        const gameUserAvatar = document.getElementById('gameUserAvatar');
        const gameUserIcon = document.getElementById('gameUserIcon');

        if (gameUserLink && gameUserAvatar && gameUserIcon) {
            if (this.isLoggedIn()) {
                if (this.currentUser.avatarUrl) {
                    gameUserAvatar.src = this.currentUser.avatarUrl;
                    gameUserAvatar.style.display = 'block';
                    gameUserIcon.style.display = 'none';
                } else {
                    gameUserAvatar.style.display = 'none';
                    gameUserIcon.style.display = 'block';
                }
                gameUserLink.href = '../pages/user.html';
            } else {
                gameUserAvatar.style.display = 'none';
                gameUserIcon.style.display = 'block';
                gameUserLink.href = '../pages/login.html';
            }
        }
    }
      
    addPoints(points) {
        if (!this.currentUser) return false;
        
        points = parseInt(points) || 0;
        const currentPoints = parseInt(this.currentUser.points) || 0;
        
        this.currentUser.points = currentPoints + points;
        localStorage.setItem('ravenStudioCurrentUser', JSON.stringify(this.currentUser));
        
        const users = JSON.parse(localStorage.getItem('ravenStudioUsers')) || [];
        const userIndex = users.findIndex(u => u.email === this.currentUser.email);
        if (userIndex !== -1) {
            users[userIndex].points = (parseInt(users[userIndex].points) || 0) + points;
            localStorage.setItem('ravenStudioUsers', JSON.stringify(users));
        }
        
        return true;
    }

    addToWishlist(productId) {
        if (!this.currentUser) return false;
        
        const users = JSON.parse(localStorage.getItem('ravenStudioUsers')) || [];
        const userIndex = users.findIndex(u => u.email === this.currentUser.email);
        
        if (userIndex !== -1) {
            if (!users[userIndex].wishlist) {
                users[userIndex].wishlist = [];
            }
            
            if (!users[userIndex].wishlist.includes(productId)) {
                users[userIndex].wishlist.push(productId);
                localStorage.setItem('ravenStudioUsers', JSON.stringify(users));
                
                this.currentUser.wishlist = users[userIndex].wishlist;
                localStorage.setItem('ravenStudioCurrentUser', JSON.stringify(this.currentUser));
                return true;
            }
        }
        return false;
    }

    logout() {
        this.currentUser = null;
        localStorage.removeItem('ravenStudioCurrentUser');
        localStorage.removeItem('ravenStudioCart');
        this.updateNavbar();
        window.location.href = '../pages/login.html';
    }

    updateNavbar() {
        const navUserLink = document.getElementById('user-nav-link');
        const navUserIcon = document.getElementById('nav-user-icon');
        const navUserAvatar = document.getElementById('nav-user-avatar');

        if (!navUserLink || !navUserIcon || !navUserAvatar) return;

        if (this.isLoggedIn()) {
            if (this.isAdmin()) {
                navUserLink.href = '../pages/admin.html';
                navUserAvatar.src = '../img/Logo.png';
                navUserAvatar.alt = 'Raven Studio Admin';
                navUserAvatar.style.display = 'block';
                navUserIcon.style.display = 'none';
            } else {
                navUserLink.href = '../pages/user.html';
                const avatarUrl = this.currentUser.avatar || this.currentUser.avatarUrl;
                if (avatarUrl) {
                    navUserAvatar.src = avatarUrl;
                    navUserAvatar.style.display = 'block';
                    navUserIcon.style.display = 'none';
                } else {
                    navUserAvatar.style.display = 'none';
                    navUserIcon.style.display = 'block';
                }
            }
        } else {
            navUserLink.href = '../pages/login.html';
            navUserAvatar.style.display = 'none';
            navUserIcon.style.display = 'block';
        }
    }

async loadUserData() {
    if (!this.isLoggedIn()) return null;

    try {
        const response = await fetch('http://localhost/trabalhofinal/finalmente/api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_user_data',
                userId: this.currentUser.id
            })
        });

        const data = await response.json();

        if (data.success && data.user) {
            // Atualiza os dados do usuário localmente
            this.currentUser = { ...this.currentUser, ...data.user };
            localStorage.setItem('ravenStudioCurrentUser', JSON.stringify(this.currentUser));
            return data.user;
        }
        return null;
    } catch (error) {
        console.error('Erro ao carregar dados do usuário:', error);
        return null;
    }
}

    removeFromWishlist(productId) {
        if (!this.currentUser || !this.currentUser.wishlist) return false;
        
        const index = this.currentUser.wishlist.indexOf(productId);
        if (index > -1) {
            this.currentUser.wishlist.splice(index, 1);
            localStorage.setItem('ravenStudioCurrentUser', JSON.stringify(this.currentUser));
            return true;
        }
        return false;
    }

    getWishlist() {
        return this.currentUser?.wishlist || [];
    }

    isLoggedIn() {
        return this.currentUser !== null;
    }

    showNotification(message) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #3b82f6, #22c55e);
            color: #000;
            padding: 1rem 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            font-family: 'Bebas Neue', cursive;
            font-size: 1.2rem;
            animation: slideIn 0.3s ease-out;
        `;
        notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

function updateCartCounter() {
    const cartItems = JSON.parse(localStorage.getItem('ravenStudioCart')) || [];
    const totalItems = cartItems.reduce((sum, item) => sum + (item.quantity || 1), 0);
    const cartCounter = document.getElementById('cart-count');
    
    if (cartCounter) {
        cartCounter.textContent = totalItems;
        cartCounter.style.display = totalItems > 0 ? 'flex' : 'none';
    }
}

function loadNavbarAvatar() {
    const user = JSON.parse(localStorage.getItem('ravenStudioCurrentUser'));
    const navUserIcon = document.getElementById('nav-user-icon');
    const navUserAvatar = document.getElementById('nav-user-avatar');

    if (user && (user.avatar || user.avatarUrl)) {
        navUserAvatar.src = user.avatar || user.avatarUrl;
        navUserAvatar.style.display = 'block';
        navUserIcon.style.display = 'none';
    } else {
        navUserAvatar.style.display = 'none';
        navUserIcon.style.display = 'block';
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', async function() {
    loadNavbarAvatar();
    window.userSession = new UserSession();
    updateCartCounter();

    const isUserPage = window.location.pathname.includes('user.html');
    
    if (isUserPage && !window.userSession.isLoggedIn()) {
        try {
            const response = await fetch('http://localhost/trabalhofinal/finalmente/api/auth.php?action=get_user_session', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            const data = await response.json();

            if (data.success && data.user) {
                window.userSession.login(data.user);
                // Chama a função para carregar os detalhes do usuário na página após o login
                // Este é o passo crucial para resolver o problema de "Carregando..."
                if (typeof loadUserDetails === 'function') {
                    loadUserDetails();
                }
            } else {
                window.location.href = '../pages/login.html';
            }
        } catch (error) {
            console.error('Erro ao buscar sessão do usuário:', error);
            window.location.href = '../pages/login.html';
        }
    } else if (!window.userSession.isLoggedIn() && window.location.pathname.includes('user.html')) {
        window.location.href = '../pages/login.html';
    }
});