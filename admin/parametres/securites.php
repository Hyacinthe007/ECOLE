            <!-- Onglet Sécurité -->
            <div id="content-securite" class="tab-content hidden">
                <div class="max-w-2xl">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Changer le mot de passe</h2>
                                    <form method="POST" class="space-y-4" novalidate>
                            <input type="hidden" name="action" value="update_password">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ancien mot de passe *</label>
                                <input type="password" name="old_password" required
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe *</label>
                                <input type="password" name="new_password" required minlength="6"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Minimum 6 caractères</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirmer le mot de passe *</label>
                                <input type="password" name="confirm_password" required minlength="6"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-key mr-2"></i> Modifier le mot de passe
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
        </main>
    </div>