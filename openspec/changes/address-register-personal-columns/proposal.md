# Why
Пользователям нужна широкая сводка по объектам и персональный набор колонок.

# What Changes
Отдельный адресный перечень на shlz-ui, read-only данные и durable account preferences.

# Capabilities
- objects/address-register: новая capability, контракт ADDRESS-REGISTER-001.

# Impact
Yii route/nav/assets, read projection и private presentation state. Без business data edits/schema migration. Production-first по поручению владельца; gates/CI deferred не GREEN.
