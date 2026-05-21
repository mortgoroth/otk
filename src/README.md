- В Дебаг можно добавить юзера по его UID прямов .env на вм, рестарт бота не нужен!
- лог /var/log/otk/vm/bot2/bot.log
---

Если создали проект с веткой main, то надо 
- создать ветку master из main, 
- запушить ее, 
- зайти в Settings->Repositories->Protected Branches, 
- там сделать UNPROTECT ветке main и ОБЯЗАТЕЛЬНО навесить protect на master. 
- После этого ветку main можно убить нахуй! 
- Переменные (http_proxy) добавить по вкусу в CI/CD->Variables, 

и тогда всё полетит

---
