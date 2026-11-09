'use strict'

import { startNotificationPoller } from '@/notification-poller'
import { createUi } from '@/ui'

startNotificationPoller()

document.addEventListener('alpine:init', createUi)
